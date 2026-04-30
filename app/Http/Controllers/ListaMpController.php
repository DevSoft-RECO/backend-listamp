<?php

namespace App\Http\Controllers;

use App\Models\ListaMp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ListaMpController extends Controller
{
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
            // Add other validations as needed
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
}
