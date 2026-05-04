<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <style>
        :root {
            --primary-color: #013d7b;
            --secondary-color: #05b622;
            --danger-color: #dc2626;
            --warning-color: #d97706;
            --light-gray: #f8f9fa;
            --medium-gray: #e9ecef;
            --dark-gray: #495057;
            --font-color: #222;
            --border-radius: 8px;
        }

        @page {
            size: Letter;
            margin: 1.5cm 1cm 1cm 1cm;

            @bottom-left {
                content: "COOPERATIVA YAMAN KUTX R.L.";
                font-size: 7pt;
                color: var(--dark-gray);
            }

            @bottom-right {
                content: "Página " counter(page) " de " counter(pages);
                font-size: 7pt;
                color: var(--dark-gray);
            }
        }

        body {
            font-family: sans-serif;
            font-size: 10pt;
            color: var(--font-color);
            margin: 0;
            padding: 0;
            line-height: 1.3;
        }

        header {
            margin-bottom: 12px;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 8px;
        }

        .header-logo {
            text-align: center;
            margin-bottom: 6px;
        }

        .logo-img {
            max-height: 50px;
            width: auto;
        }

        .logo h1 {
            margin: 0;
            font-size: 12pt;
            font-weight: 700;
            color: var(--primary-color);
            text-align: center;
        }

        .textlogo h1 {
            margin: 0;
            font-size: 12pt;
            font-weight: 700;
            color: var(--secondary-color);
            text-align: center;
        }

        .header-main-info {
            background-color: var(--light-gray);
            border-radius: var(--border-radius);
            padding: 8px 12px;
            margin-bottom: 8px;
            border-left: 4px solid var(--primary-color);
            font-size: 9pt;
        }

        .header-main-info div { margin-bottom: 3px; }
        .header-main-info b { color: var(--primary-color); }

        .header-bottom {
            background-color: var(--medium-gray);
            border-radius: var(--border-radius);
            padding: 8px 12px;
            font-size: 8.5pt;
        }
        
        .header-bottom table { width: 100%; border: none; margin-top: 0; }
        .header-bottom td { border: none; padding: 2px 0; vertical-align: top; }
        .header-bottom b { color: var(--primary-color); }

        main { min-height: 50vh; }

        .result-section { margin-bottom: 15px; page-break-inside: auto; }
        .section-title {
            background-color: var(--primary-color);
            color: white;
            padding: 6px 10px;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            font-weight: 600;
            font-size: 10pt;
            margin-bottom: 0;
            page-break-after: avoid;
        }

        .result-content {
            border: 1px solid var(--medium-gray);
            border-top: none;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            padding: 8px;
            background-color: white;
            page-break-inside: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            page-break-inside: auto;
        }

        th, td {
            text-align: left;
            padding: 7px 8px;
            border-bottom: 1px solid var(--medium-gray);
            font-size: 8.5pt;
        }

        tr { page-break-inside: avoid; page-break-after: auto; }
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }

        th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 8pt;
        }

        tbody tr:last-child td { border-bottom: none; }

        .no-data {
            text-align: center;
            padding: 15px;
            color: var(--dark-gray);
            font-style: italic;
            font-size: 9pt;
        }

        .validation-status {
            padding: 8px 12px;
            border-radius: var(--border-radius);
            font-weight: 600;
            margin-bottom: 10px;
            text-align: center;
            font-size: 9.5pt;
        }

        .status-info {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .authorization-section { margin-bottom: 12px; }
        .authorization-header {
            background-color: #92400e;
            color: white;
            padding: 6px 10px;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            font-weight: 600;
            font-size: 9.5pt;
        }

        .authorization-content {
            border: 1px solid var(--medium-gray);
            border-top: none;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            padding: 10px;
            background-color: #fffbeb;
            font-size: 9pt;
        }

        .authorization-field { margin-bottom: 5px; }
        .authorization-label { font-weight: 600; color: var(--primary-color); display: inline-block; width: 80px; }
        .authorization-text { display: inline; }

        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: calc(100% - 2cm);
            margin: 0 1cm;
            padding: 8px 0;
            font-size: 7.5pt;
            color: var(--dark-gray);
            border-top: 1px solid var(--medium-gray);
            background-color: white;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .footer-content div { width: 48%; }
        footer b { color: var(--primary-color); }
        .bottom-text { text-align: center; font-size: 7pt; margin-top: 4px; border-top: 1px solid var(--medium-gray); padding-top: 4px; }
    </style>
</head>

<body>
    <header>
        <div class="header-logo">
            {{-- Se corrigió la ruta del logo a assets/reportes/ --}}
            <img src="{{ public_path('assets/reportes/logoyk.svg') }}" alt="Logo Cooperativa Yaman Kutx" class="logo-img">
        </div>
        <div class="logo">
            <h1>COOPERATIVA YAMAN KUTX R.L.</h1>
        </div>
        <div class="textlogo">
            <h1>VALIDACIÓN DE LISTA NEGRA</h1>
        </div>

        <div class="header-main-info">
            <div><b>Consulta Realizada:</b> {{ $nombre_consultado }}</div>
            @if (isset($documento) && $documento)
                <div><b>CUI/DPI Consultado/Validado:</b> {{ $documento }}</div>
            @endif
        </div>

        <div class="header-bottom">
            <table style="width: 100%;">
                <tr>
                    <td><b>Usuario:</b> {{ $usuario['name'] ?? 'N/A' }}</td>
                    <td style="text-align: right;"><b>Fecha y Hora:</b> {{ $fecha_consulta }}</td>
                </tr>
                <tr>
                    <td><b>Tipo:</b> {{ $usuario['role'] ?? 'N/A' }}</td>
                    <td style="text-align: right;"><b>Agencia:</b> {{ $usuario['agency'] ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>
    </header>

    <main>
        {{-- Mensaje de validación general --}}
        @if ($mensaje_extra)
            <div class="validation-status status-info">
                {{ $mensaje_extra }}
            </div>
        @endif

        {{-- SECCIÓN DE AUTORIZACIÓN --}}
        @if ($requiere_autorizacion && $forzar_excepcion)
            <section class="authorization-section">
                <div class="authorization-header">Comentarios para autorización:</div>
                <div class="authorization-content">
                    @if (($destinatario == 'cumplimiento' || $destinatario == 'ambos') && !empty($observacion_cumplimiento))
                        <div class="authorization-field">
                            <span class="authorization-label">Lista MP:</span>
                            <span class="authorization-text">{{ $observacion_cumplimiento }}</span>
                        </div>
                    @endif

                    @if (($destinatario == 'jefatura' || $destinatario == 'ambos') && !empty($observacion_jefatura))
                        <div class="authorization-field">
                            <span class="authorization-label">Lista Crédito:</span>
                            <span class="authorization-text">{{ $observacion_jefatura }}</span>
                        </div>
                    @endif
                </div>
            </section>
        @elseif($requiere_autorizacion)
            <section class="authorization-section">
                <div class="authorization-header">REQUIERE AUTORIZACIÓN</div>
                <div class="authorization-content" style="background-color: #fee2e2; text-align: center;">
                    <strong>⚠️ COINCIDENCIAS DETECTADAS - REQUIERE AUTORIZACIÓN PARA PROCEDER</strong>
                </div>
            </section>
        @endif

        {{-- Tabla MP --}}
        <section class="result-section">
            <div class="section-title">LISTA MP - MINISTERIO PÚBLICO ({{ $registrosMP->count() }} Coincidencias)</div>
            <div class="result-content">
                @if ($registrosMP->isNotEmpty())
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>CUI (MP)</th>
                                <th>Pasaporte</th>
                                <th>NIT</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($registrosMP as $registro)
                                <tr>
                                    <td>{{ $registro['nombre'] ?? 'N/A' }}</td>
                                    <td>{{ $registro['documento'] ?? ($registro['cui'] ?? '-') }}</td>
                                    <td>{{ $registro['pasaporte'] ?? '-' }}</td>
                                    <td>{{ $registro['nit'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="no-data">No se encontraron registros en la Lista MP.</div>
                @endif
            </div>
        </section>

        {{-- Tabla Créditos --}}
        <section class="result-section">
            <div class="section-title">LISTA NEGRA DE CRÉDITOS ({{ $registrosCreditos->count() }} Coincidencias)</div>
            <div class="result-content">
                @if ($registrosCreditos->isNotEmpty())
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>DPI (Créditos)</th>
                                <th>Motivo</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($registrosCreditos as $registro)
                                <tr>
                                    <td>{{ $registro['nombre'] ?? 'N/A' }}</td>
                                    <td>{{ $registro['documento'] ?? ($registro['dpi'] ?? '-') }}</td>
                                    <td>{{ $registro['motivo'] ?? 'N/A' }}</td>
                                    <td>{{ $registro['descripcion'] ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="no-data">No se encontraron registros en la Lista Negra de Créditos.</div>
                @endif
            </div>
        </section>
    </main>

    <footer>
        <div class="footer-content">
            <div>
                <b>Documento Autenticado</b>
                <p>Sistema oficial de consultas - Cooperativa Yaman Kutx R.L.</p>
            </div>
            <div>
                <b>Área de Informática</b>
                <p>Corporativo Central - Sistema de Generación de Reportes</p>
            </div>
        </div>
        <div class="bottom-text">
            Reporte generado automáticamente - {{ $fecha_consulta }} | <strong>SISTEMA MP Y CRÉDITOS</strong>
        </div>
    </footer>
</body>

</html>
