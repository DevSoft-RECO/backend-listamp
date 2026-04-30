<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fiscalia;

class FiscaliaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Fiscalia::orderBy('nombre')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:fiscalias,nombre',
        ]);

        $fiscalia = Fiscalia::create([
            'nombre' => $request->nombre,
        ]);

        return response()->json([
            'message' => 'Fiscalía creada correctamente',
            'data' => $fiscalia
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Fiscalia $fiscalia)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:fiscalias,nombre,' . $fiscalia->id,
        ]);

        $fiscalia->update([
            'nombre' => $request->nombre,
        ]);

        return response()->json([
            'message' => 'Fiscalía actualizada correctamente',
            'data' => $fiscalia
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Fiscalia $fiscalia)
    {
        $fiscalia->delete();

        return response()->json([
            'message' => 'Fiscalía eliminada correctamente'
        ]);
    }
}
