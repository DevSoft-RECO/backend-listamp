<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ListaCredito;

class ListaCreditoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ListaCredito::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'LIKE', "%{$search}%")
                  ->orWhere('dpi', 'LIKE', "%{$search}%")
                  ->orWhere('motivo', 'LIKE', "%{$search}%");
            });
        }

        $pagination = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'pagination' => $pagination,
            'stats' => [
                'total' => ListaCredito::count(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'id_usuario' => 'required|string|max:100',
            'dpi' => 'nullable|string|max:20',
            'motivo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $item = ListaCredito::create($request->all());

        return response()->json([
            'message' => 'Registro creado correctamente',
            'data' => $item
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ListaCredito $lista_credito)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'id_usuario' => 'required|string|max:100',
            'dpi' => 'nullable|string|max:20',
            'motivo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $lista_credito->update($request->all());

        return response()->json([
            'message' => 'Registro actualizado correctamente',
            'data' => $lista_credito
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ListaCredito $lista_credito)
    {
        $lista_credito->delete();

        return response()->json([
            'message' => 'Registro eliminado correctamente'
        ]);
    }

    /**
     * Export the listing to CSV.
     */
    public function exportCSV(Request $request)
    {
        $query = ListaCredito::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'LIKE', "%{$search}%")
                  ->orWhere('dpi', 'LIKE', "%{$search}%")
                  ->orWhere('motivo', 'LIKE', "%{$search}%");
            });
        }

        $data = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=lista_negra_creditos.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['ID', 'Nombre', 'Identificación (DPI)', 'Código Usuario', 'Motivo', 'Descripción', 'Fecha Registro'];

        $callback = function() use($data, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($data as $item) {
                fputcsv($file, [
                    $item->id,
                    $item->nombre,
                    $item->dpi,
                    $item->id_usuario,
                    $item->motivo,
                    $item->descripcion,
                    $item->created_at,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
