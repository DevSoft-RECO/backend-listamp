<?php

namespace App\Traits;

use App\Models\ListaMp;
use App\Models\ListaCredito;
use Illuminate\Support\Collection;

trait ValidacionDocumentos
{
    /**
     * Validación por CUI
     */
    private function validarPorCUI(string $documentoIngresado, Collection $registrosVisibles)
    {
        $registroCoincidente = $registrosVisibles->first(function ($item) use ($documentoIngresado) {
            $cui = is_array($item) ? ($item['cui'] ?? null) : ($item->cui ?? null);
            return $cui && preg_replace('/[\s-]+/', '', $cui) === $documentoIngresado;
        });

        if ($registroCoincidente) {
            return [
                collect([$registroCoincidente]),
                "Coincidencia Encontrada.",
                is_array($registroCoincidente) ? ($registroCoincidente['cui'] ?? null) : ($registroCoincidente->cui ?? null),
                is_array($registroCoincidente) ? ($registroCoincidente['pasaporte'] ?? null) : ($registroCoincidente->pasaporte ?? null),
                is_array($registroCoincidente) ? ($registroCoincidente['nit'] ?? null) : ($registroCoincidente->nit ?? null),
            ];
        }

        $coincidenciasDB = ListaMp::where('estado', '1')
            ->whereRaw("REPLACE(REPLACE(cui, ' ', ''), '-', '') = ?", [$documentoIngresado])
            ->get();

        if ($coincidenciasDB->isNotEmpty()) {
            return [
                $coincidenciasDB,
                "El CUI '{$documentoIngresado}' está en la base de datos, aunque no se muestra en la búsqueda.",
                $coincidenciasDB->first()->cui ?? null,
                $coincidenciasDB->first()->pasaporte ?? null,
                $coincidenciasDB->first()->nit ?? null,
            ];
        }

        return [
            collect(),
            "No se encontró CUI '{$documentoIngresado}' en la base de datos.",
            null,
            null,
            null,
        ];
    }

    /**
     * Validación por Pasaporte
     */
    private function validarPorPasaporte(string $documentoIngresado, Collection $registrosVisibles)
    {
        $registroCoincidente = $registrosVisibles->first(function ($item) use ($documentoIngresado) {
            $pasaporte = is_array($item) ? ($item['pasaporte'] ?? null) : ($item->pasaporte ?? null);
            return $pasaporte && preg_replace('/[\s-]+/', '', $pasaporte) === $documentoIngresado;
        });

        if ($registroCoincidente) {
            return [
                collect([$registroCoincidente]),
                "Coincidencia exacta encontrada para Pasaporte '{$documentoIngresado}'.",
                is_array($registroCoincidente) ? ($registroCoincidente['cui'] ?? null) : ($registroCoincidente->cui ?? null),
                is_array($registroCoincidente) ? ($registroCoincidente['pasaporte'] ?? null) : ($registroCoincidente->pasaporte ?? null),
                is_array($registroCoincidente) ? ($registroCoincidente['nit'] ?? null) : ($registroCoincidente->nit ?? null),
            ];
        }

        $coincidenciasDB = ListaMp::where('estado', '1')
            ->whereRaw("REPLACE(REPLACE(pasaporte, ' ', ''), '-', '') = ?", [$documentoIngresado])
            ->get();

        if ($coincidenciasDB->isNotEmpty()) {
            return [
                $coincidenciasDB,
                "El pasaporte '{$documentoIngresado}' está en la base de datos, aunque no se muestra en la búsqueda.",
                $coincidenciasDB->first()->cui ?? null,
                $coincidenciasDB->first()->pasaporte ?? null,
                $coincidenciasDB->first()->nit ?? null,
            ];
        }

        return [
            collect(),
            "No se encontró Pasaporte '{$documentoIngresado}' en la base de datos.",
            null,
            null,
            null,
        ];
    }

    /**
     * Validación por NIT
     */
    private function validarPorNIT(string $documentoIngresado, Collection $registrosVisibles)
    {
        $documentoLimpio = strtoupper(preg_replace('/[\s-]+/', '', $documentoIngresado));

        $registroCoincidente = $registrosVisibles->first(function ($item) use ($documentoLimpio) {
            $nit = is_array($item) ? ($item['nit'] ?? null) : ($item->nit ?? null);
            if (!$nit) return false;
            return strtoupper(preg_replace('/[\s-]+/', '', $nit)) === $documentoLimpio;
        });

        if ($registroCoincidente) {
            return [
                collect([$registroCoincidente]),
                "Coincidencia exacta encontrada para NIT '{$documentoIngresado}'.",
                is_array($registroCoincidente) ? ($registroCoincidente['cui'] ?? null) : ($registroCoincidente->cui ?? null),
                is_array($registroCoincidente) ? ($registroCoincidente['pasaporte'] ?? null) : ($registroCoincidente->pasaporte ?? null),
                is_array($registroCoincidente) ? ($registroCoincidente['nit'] ?? null) : ($registroCoincidente->nit ?? null),
            ];
        }

        $coincidenciasDB = ListaMp::where('estado', '1')
            ->whereRaw("UPPER(REPLACE(REPLACE(nit, ' ', ''), '-', '')) = ?", [$documentoLimpio])
            ->get();

        if ($coincidenciasDB->isNotEmpty()) {
            return [
                $coincidenciasDB,
                "El NIT '{$documentoIngresado}' está en la base de datos, aunque no se muestra en la búsqueda.",
                $coincidenciasDB->first()->cui ?? null,
                $coincidenciasDB->first()->pasaporte ?? null,
                $coincidenciasDB->first()->nit ?? null,
            ];
        }

        return [
            collect(),
            "No se encontró NIT '{$documentoIngresado}' en la base de datos.",
            null,
            null,
            null,
        ];
    }

    /**
     * Limpia el nombre: quita tildes, caracteres especiales, y convierte a mayúsculas.
     */
    private function cleanName(string $name): string
    {
        $unaccented = strtr(utf8_decode($name),
            utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'),
            'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');

        $cleaned = preg_replace('/[^A-Za-z0-9\s]/', '', $unaccented);
        return strtoupper(trim($cleaned));
    }

    /**
     * Limpia el documento: quita espacios y guiones.
     */
    private function cleanDoc(string $doc): string
    {
        return str_replace([' ', '-'], '', $doc);
    }
}
