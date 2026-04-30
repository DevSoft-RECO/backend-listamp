<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>

    <style>
        /* Tipografía Montserrat via Google Fonts (DOMPDF soporta fuentes externas si están habilitadas, de lo contrario usará Helvetica) */
        @page {
            size: Letter;
            margin: 1.5cm 1.5cm 1.2cm 1.5cm;
        }

        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 10.5pt;
            color: #222;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }

        /* Variables de color emuladas */
        .color-primary { color: #013d7b; }
        .color-secondary { color: #05b622; }

        header { margin-bottom: 15px; border-bottom: 2px solid #013d7b; padding-bottom: 10px; }
        .header-logo { text-align: center; margin-bottom: 10px; }
        .logo-img { max-height: 60px; width: auto; }
        
        .logo h1, .textlogo h1 { margin: 0; font-size: 14pt; font-weight: bold; text-align: center; }
        .logo h1 { color: #013d7b; }
        .textlogo h1 { color: #05b622; }

        .header-main-info { background-color: #f8f9fa; border-radius: 8px; padding: 8px 12px; margin-bottom: 8px; border: 1px solid #e9ecef; }
        .header-main-info b { color: #013d7b; }

        .header-bottom { 
            width: 100%;
            background-color: #e9ecef; 
            border-radius: 8px; 
            padding: 8px 12px;
            font-size: 9pt;
        }
        .header-bottom td { vertical-align: top; border: none; padding: 0; }
        .header-bottom b { color: #013d7b; }

        main { min-height: 500px; }

        .result-section {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            background-color: white;
            margin-top: 15px;
        }

        .result-section h2 { font-size: 12pt; color: #013d7b; margin-top: 0; margin-bottom: 5px; }
        .result-section p { font-size: 9pt; color: #495057; margin: 0; }

        table.content-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        table.content-table th {
            background-color: #013d7b;
            color: white;
            text-align: left;
            padding: 10px;
            font-size: 9.5pt;
            text-transform: uppercase;
        }

        table.content-table td {
            text-align: left;
            padding: 8px 10px;
            border-bottom: 1px solid #e9ecef;
            font-size: 9.5pt;
        }

        .no-result {
            text-align: center;
            margin: 30px auto;
            color: #495057;
            font-weight: bold;
            font-size: 11pt;
        }

        .mensaje-extra {
            margin-top: 15px;
            padding: 10px;
            border: 1px dashed #495057;
            background-color: #fff3cd;
            font-size: 9.5pt;
            text-align: center;
        }

        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: white;
            border-top: 1px solid #e9ecef;
            padding: 10px 0;
            font-size: 8.5pt;
            color: #495057;
        }

        .footer-table { width: 100%; }
        .footer-table td { width: 50%; vertical-align: top; }
        footer b { color: #013d7b; font-size: 9pt; }
        footer ul { list-style: none; margin: 0; padding: 0; }

        .bottom-text {
            text-align: center;
            font-size: 8pt;
            color: #495057;
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px solid #e9ecef;
        }
    </style>
</head>

<body>
<header>
    <div class="header-logo">
        <img src="{{ public_path('assets/reportes/logoyk.svg') }}" alt="Logo Cooperativa" class="logo-img">
    </div>
    <div class="logo">
        <h1>COOPERATIVA YAMAN KUTX R.L.</h1>
    </div>
    <div class="textlogo">
        <h1>VALIDACIÓN DE LISTA NEGRA</h1>
    </div>

    <div class="header-main-info">
        <b>Consulta Realizada:</b> {{ $nombre_consultado ?? '-' }}<br>
        <b>Forma de Validación:</b> {{ $tipo_identificacion }}<br>
        <b>Documento ingresado para validación:</b> {{ $documento_ingresado ?? 'Verificación sin Documento' }}
    </div>

    <table class="header-bottom">
        <tr>
            <td>
                <b>Usuario Consultor:</b> {{ $nombre_usuario }}<br>
                <b>Tipo de Usuario:</b> {{ $tipo_usuario }}<br>
                <b>Agencia:</b> {{ $agencia_usuario }}
            </td>
            <td style="text-align: right;">
                <b>Hora:</b> {{ $hora_consulta }}
            </td>
        </tr>
    </table>
</header>

<main>
    <section class="result-section">
        <h2>{{ $titulo }}</h2>
        <p>Fecha de emisión: {{ $fecha_consulta }}</p>

        @if($hay_resultados)
            <table class="content-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>CUI/DPI</th>
                        <th>Pasaporte</th>
                        <th>NIT</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($coincidencias as $persona)
                        <tr>
                            <td>{{ is_array($persona) ? ($persona['nombre'] ?? '-') : ($persona->nombre ?? '-') }}</td>
                            <td>{{ is_array($persona) ? ($persona['cui'] ?? '-') : ($persona->cui ?? '-') }}</td>
                            <td>{{ is_array($persona) ? ($persona['pasaporte'] ?? '-') : ($persona->pasaporte ?? '-') }}</td>
                            <td>{{ is_array($persona) ? ($persona['nit'] ?? '-') : ($persona->nit ?? '-') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="no-result">
                APTO - No se encontraron registros en la lista para la persona buscada.
            </div>
        @endif

        @if($mensaje_extra)
            <div class="mensaje-extra">
                <b>Aviso:</b> {!! $mensaje_extra !!}
            </div>
        @endif
    </section>
</main>

<footer>
    <table class="footer-table">
        <tr>
            <td>
                <b>Documento Autenticado</b>
                <p>Este reporte es válido y emitido por el sistema oficial de consultas de la Cooperativa Yaman Kutx R.L.</p>
            </td>
            <td style="text-align: right;">
                <b>Área de Informática</b>
                <ul>
                    <li>Corporativo Central</li>
                    <li>Sistema de Generación de Reportes</li>
                </ul>
            </td>
        </tr>
    </table>
    <div class="bottom-text">
        Certificado de reporte para validar si la persona se encuentra en la lista del Ministerio Público.
    </div>
</footer>

</body>
</html>
