<?php

namespace App\Http\Controllers;

use App\Models\ListaMp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ListaMpController extends Controller
{
    /**
     * Export all records to CSV.
     */
    public function exportCSV()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="reporte_lista_mp_' . date('Y-m-d_His') . '.csv"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            // UTF-8 BOM for Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($file, [
                'ID', 'Nombre', 'Tipo Identificación', 'Registro', 'CUI', 'Pasaporte', 
                'Lugar Origen', 'Fecha Respuesta', 'NIT', 'Fecha Oficio', 'Oficio', 
                'Tipo P', 'Fiscalía', 'Fecha Cooperativa', 'Fecha Cumplimiento', 
                'Estado', '¿Es Asociado?', 'Motivo de Baja', 'Fecha Creación', 'Última Actualización'
            ], ';');

            // Data
            ListaMp::chunk(200, function($records) use ($file) {
                foreach ($records as $record) {
                    fputcsv($file, [
                        $record->iddatos,
                        $record->nombre,
                        $record->tipo_identificacion,
                        $record->registro,
                        $record->cui,
                        $record->pasaporte,
                        $record->lugar_origen,
                        $record->fecha_respuesta,
                        $record->nit,
                        $record->fecha_of,
                        $record->oficio,
                        $record->tipo_p,
                        $record->fiscalia,
                        $record->fecha_cooperativa,
                        $record->fecha_cumplimiento,
                        $record->estado == '1' ? 'Activo' : 'Inactivo',
                        $record->es_asociado,
                        $record->observacion_baja,
                        $record->created_at,
                        $record->updated_at
                    ], ';');
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ListaMp::query();

        // Server-side Filtering
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('cui', 'like', "%{$search}%")
                  ->orWhere('nit', 'like', "%{$search}%")
                  ->orWhere('pasaporte', 'like', "%{$search}%");
            });
        }

        if ($request->has('estado') && $request->estado !== 'all') {
            $query->where('estado', $request->estado);
        }

        $listas = $query->orderBy('iddatos', 'desc')->paginate(10);
        
        return response()->json([
            'pagination' => $listas,
            'stats' => [
                'total' => ListaMp::count(),
                'inactive' => ListaMp::where('estado', '0')->count(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'fecha_respuesta' => 'required|date',
            'es_asociado' => 'nullable|in:SI,NO,Pendiente',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $lista = ListaMp::create($request->all());

        return response()->json([
            'message' => 'Registro creado exitosamente',
            'data' => $lista
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $lista = ListaMp::findOrFail($id);
        return response()->json($lista);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $lista = ListaMp::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'fecha_respuesta' => 'required|date',
            'es_asociado' => 'nullable|in:SI,NO,Pendiente',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $lista->update($request->all());

        return response()->json([
            'message' => 'Registro actualizado exitosamente',
            'data' => $lista
        ]);
    }

    /**
     * Deactivate the specified resource (Historical record).
     */
    public function destroy(Request $request, $id)
    {
        $lista = ListaMp::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'observacion_baja' => 'required|string|min:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $lista->update([
            'estado' => '0',
            'observacion_baja' => $request->observacion_baja
        ]);

        return response()->json([
            'message' => 'Registro desactivado (Baja) exitosamente',
            'data' => $lista
        ]);
    }

    /**
     * Dar de baja (desactivar) con soporte para subir archivo PDF de respaldo.
     */
    public function darBaja(Request $request, $id)
    {
        $lista = ListaMp::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'observacion_baja' => 'required|string|min:5',
            'documento_baja' => 'nullable|file|mimes:pdf|max:10240', // Max 10MB PDF
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [
            'estado' => '0',
            'observacion_baja' => $request->observacion_baja
        ];

        if ($request->hasFile('documento_baja')) {
            $file = $request->file('documento_baja');
            $filename = 'baja_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $directorio = 'uploads/documentos_baja';

            if (!\Illuminate\Support\Facades\File::exists(public_path($directorio))) {
                \Illuminate\Support\Facades\File::makeDirectory(public_path($directorio), 0755, true);
            }

            $file->move(public_path($directorio), $filename);
            $data['documento_baja'] = $directorio . '/' . $filename;
        }

        $lista->update($data);

        return response()->json([
            'message' => 'Registro desactivado (Baja) exitosamente',
            'data' => $lista
        ]);
    }
}
