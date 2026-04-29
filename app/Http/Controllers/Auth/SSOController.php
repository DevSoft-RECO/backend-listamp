<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Agencia;
use Illuminate\Http\JsonResponse;

class SSOController extends Controller
{
    /**
     * Sincroniza el perfil JIT (Just-In-Time) con la App Madre.
     * Esta función es el corazón del ecosistema para obtener identidad, roles y permisos.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $motherUrl = env('APP_MADRE_URL', 'http://localhost:8000');

        try {
            // 1. Consultar a la Madre usando el mismo Bearer Token
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$motherUrl}/api/me");

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Fallo en la sincronización con el ecosistema (Madre)',
                    'error' => $response->reason()
                ], 502);
            }

            $userData = $response->json();
            
            // Desempaquetar si viene en 'data' (Laravel Resources)
            if (isset($userData['data'])) {
                $userData = $userData['data'];
            }

            // 2. APLANAMIENTO CRÍTICO (Spatie Objects -> Simple Strings)
            $userData['roles'] = $this->flatten($userData['roles'] ?? []);
            $userData['permisos'] = $this->flatten($userData['permisos'] ?? []);
            
            // 3. Extracción de JTI del Token (para mirroring con Go)
            $jti = null;
            $tokenParts = explode('.', $token);
            if (count($tokenParts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
                $jti = $payload['jti'] ?? null;
            }

            // 4. SINCRONIZACIÓN JIT (Just-In-Time)
            
            // Upsert Agencia
            if (isset($userData['agencia'])) {
                $agData = $userData['agencia'];
                Agencia::updateOrCreate(
                    ['id' => $agData['id']],
                    [
                        'nombre'    => $agData['nombre'],
                        'codigo'    => $agData['codigo'] ?? null,
                        'codigot24' => $agData['codigot24'] ?? null,
                        'direccion' => $agData['direccion'] ?? null,
                    ]
                );
            }

            // Upsert User
            User::updateOrCreate(
                ['id' => $userData['id']],
                [
                    'name'             => $userData['name'],
                    'username'         => $userData['username'] ?? null,
                    'email'            => $userData['email'],
                    'telefono'         => $userData['telefono'] ?? null,
                    'id_agencia'       => $userData['idagencia'] ?? null,
                    'avatar'           => $userData['avatar'] ?? null,
                    'roles_list'       => $userData['roles'],
                    'permissions_list' => $userData['permisos'],
                    'jti'              => $jti,
                ]
            );

            // 5. Fallbacks de estandarización para el Frontend
            $userData['roles_list'] = $userData['roles'];
            $userData['permissions'] = $userData['permisos'];
            $userData['_source'] = 'madre_sync';

            return response()->json($userData);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno de comunicación SSO',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convierte colecciones de objetos de roles/permisos (Spatie) en arreglos de strings.
     * 
     * @param mixed $items
     * @return array
     */
    private function flatten($items): array
    {
        if (!is_array($items)) return [];

        return array_map(function ($item) {
            return is_array($item) ? ($item['name'] ?? $item) : $item;
        }, $items);
    }
}
