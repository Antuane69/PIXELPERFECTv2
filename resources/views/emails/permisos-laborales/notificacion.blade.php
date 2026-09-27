<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Permiso laboral</title>
</head>
<body style="margin:0;background:#f4f4f5;color:#18181b;font-family:Arial,sans-serif;line-height:1.5">
    <main style="max-width:680px;margin:24px auto;padding:24px;background:#fff;border:1px solid #e4e4e7;border-radius:12px">
        <p style="margin:0 0 8px;color:#71717a">{{ $datos['empresa'] }}</p>
        <h1 style="margin:0 0 16px;font-size:22px">
            {{ $tipo === 'solicitud' ? 'Nueva solicitud de permiso laboral' : 'Resolución del permiso laboral' }}
        </h1>

        <table style="width:100%;border-collapse:collapse">
            <tbody>
                <tr><th scope="row" style="padding:8px;text-align:left">Empleado</th><td style="padding:8px">{{ $datos['empleado'] }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Tipo de permiso</th><td style="padding:8px">{{ $datos['tipo_permiso'] }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Solicitante</th><td style="padding:8px">{{ $datos['solicitante'] }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Periodo</th><td style="padding:8px">{{ $datos['fecha_inicio'] }} al {{ $datos['fecha_fin'] }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Estatus</th><td style="padding:8px">{{ $datos['estado'] }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Cobertura</th><td style="padding:8px">{{ count($datos['coberturas']) ? implode(', ', $datos['coberturas']) : 'Sin cobertura registrada' }}</td></tr>
            </tbody>
        </table>

        @if ($datos['comentarios'])
            <h2 style="margin:20px 0 8px;font-size:16px">Comentarios de la solicitud</h2>
            <p style="margin:0;white-space:pre-wrap">{{ $datos['comentarios'] }}</p>
        @endif

        @if ($tipo === 'resolucion')
            @if ($datos['resuelto_por'])
                <p style="margin:20px 0 0">Revisó: {{ $datos['resuelto_por'] }}</p>
            @endif
            @if ($datos['comentarios_rechazo'])
                <h2 style="margin:20px 0 8px;font-size:16px">Motivo del rechazo</h2>
                <p style="margin:0;white-space:pre-wrap">{{ $datos['comentarios_rechazo'] }}</p>
            @endif
        @endif

        @if ($tipo === 'solicitud' && count($datos['coincidencias']))
            <h2 style="margin:24px 0 8px;font-size:16px">Solicitudes que coinciden en fechas</h2>
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        <th scope="col" style="padding:8px;text-align:left;border-bottom:1px solid #e4e4e7">Empleado</th>
                        <th scope="col" style="padding:8px;text-align:left;border-bottom:1px solid #e4e4e7">Tipo de permiso</th>
                        <th scope="col" style="padding:8px;text-align:left;border-bottom:1px solid #e4e4e7">Periodo</th>
                        <th scope="col" style="padding:8px;text-align:left;border-bottom:1px solid #e4e4e7">Estatus</th>
                        <th scope="col" style="padding:8px;text-align:left;border-bottom:1px solid #e4e4e7">Cobertura</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($datos['coincidencias'] as $coincidencia)
                        <tr>
                            <td style="padding:8px;border-bottom:1px solid #e4e4e7">{{ $coincidencia['empleado'] }}</td>
                            <td style="padding:8px;border-bottom:1px solid #e4e4e7">{{ $coincidencia['tipo_permiso'] ?? 'Sin tipo registrado' }}</td>
                            <td style="padding:8px;border-bottom:1px solid #e4e4e7">{{ $coincidencia['fecha_inicio'] }} al {{ $coincidencia['fecha_fin'] }}</td>
                            <td style="padding:8px;border-bottom:1px solid #e4e4e7">{{ $coincidencia['estado'] }}</td>
                            <td style="padding:8px;border-bottom:1px solid #e4e4e7">{{ count($coincidencia['coberturas']) ? implode(', ', $coincidencia['coberturas']) : 'Sin cobertura registrada' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif ($tipo === 'solicitud')
            <p style="margin:24px 0 0;color:#71717a">No se encontraron solicitudes pendientes o autorizadas que coincidan con este periodo.</p>
        @endif
    </main>
</body>
</html>
