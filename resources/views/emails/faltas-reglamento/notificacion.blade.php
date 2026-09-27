<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Falta al reglamento</title>
</head>
<body style="margin:0;background:#f4f4f5;color:#18181b;font-family:Arial,sans-serif;line-height:1.5">
    <main style="max-width:720px;margin:24px auto;padding:24px;background:#fff;border:1px solid #e4e4e7;border-radius:12px">
        <p style="margin:0 0 8px;color:#71717a">{{ $datos['empresa'] }}</p>
        <h1 style="margin:0 0 16px;font-size:22px">
            {{ $tipo === 'solicitud' ? 'Nuevo reporte de falta al reglamento' : 'Resolución del reporte de falta al reglamento' }}
        </h1>

        <h2 style="margin:20px 0 8px;font-size:16px">Información del registro</h2>
        <table style="width:100%;border-collapse:collapse">
            <tbody>
                <tr><th scope="row" style="padding:8px;text-align:left">Folio</th><td style="padding:8px">{{ $datos['registro']['id'] }}</td></tr>
                @if ($datos['registro']['fecha_registro'])
                    <tr><th scope="row" style="padding:8px;text-align:left">Fecha de registro</th><td style="padding:8px">{{ $datos['registro']['fecha_registro'] }}</td></tr>
                @endif
                <tr><th scope="row" style="padding:8px;text-align:left">Fecha de ocurrencia</th><td style="padding:8px">{{ $datos['registro']['fecha_ocurrencia'] }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Tipo de falta</th><td style="padding:8px">{{ $datos['registro']['tipo_falta'] }}</td></tr>
                @if ($datos['registro']['descripcion_tipo_falta'])
                    <tr><th scope="row" style="padding:8px;text-align:left">Descripción del tipo</th><td style="padding:8px;white-space:pre-wrap">{{ $datos['registro']['descripcion_tipo_falta'] }}</td></tr>
                @endif
                <tr><th scope="row" style="padding:8px;text-align:left">Falta cometida</th><td style="padding:8px">{{ $datos['registro']['falta'] }}</td></tr>
                @if ($datos['registro']['descripcion_falta'])
                    <tr><th scope="row" style="padding:8px;text-align:left">Descripción</th><td style="padding:8px;white-space:pre-wrap">{{ $datos['registro']['descripcion_falta'] }}</td></tr>
                @endif
                <tr><th scope="row" style="padding:8px;text-align:left">Estado</th><td style="padding:8px">{{ $datos['registro']['estado'] }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Reportó</th><td style="padding:8px">{{ $datos['registro']['solicitante'] }} ({{ $datos['registro']['correo_solicitante'] }})</td></tr>
                @if ($datos['registro']['resuelto_por'])
                    <tr><th scope="row" style="padding:8px;text-align:left">Revisó</th><td style="padding:8px">{{ $datos['registro']['resuelto_por'] }}</td></tr>
                @endif
                @if ($datos['registro']['resuelto_at'])
                    <tr><th scope="row" style="padding:8px;text-align:left">Fecha de resolución</th><td style="padding:8px">{{ $datos['registro']['resuelto_at'] }}</td></tr>
                @endif
            </tbody>
        </table>

        <h2 style="margin:20px 0 8px;font-size:16px">Empleado afectado</h2>
        <table style="width:100%;border-collapse:collapse">
            <tbody>
                <tr><th scope="row" style="padding:8px;text-align:left">Expediente</th><td style="padding:8px">{{ $datos['empleado']['id'] }} ({{ $datos['empleado']['expediente'] }})</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Nombre</th><td style="padding:8px">{{ $datos['empleado']['nombre'] }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Usuario</th><td style="padding:8px">{{ $datos['empleado']['usuario'] ?: 'No registrado' }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Nombre de cuenta</th><td style="padding:8px">{{ $datos['empleado']['nombre_cuenta'] ?: 'Sin cuenta vinculada' }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Correo del empleado</th><td style="padding:8px">{{ $datos['empleado']['correo'] ?: 'No registrado' }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Correo de la cuenta</th><td style="padding:8px">{{ $datos['empleado']['correo_cuenta'] ?: 'No vinculado' }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Puesto</th><td style="padding:8px">{{ $datos['empleado']['puesto'] ?: 'Sin puesto registrado' }}</td></tr>
            </tbody>
        </table>

        <h2 style="margin:20px 0 8px;font-size:16px">Comentarios del reporte</h2>
        <p style="margin:0;white-space:pre-wrap">{{ $datos['registro']['comentarios'] ?: 'Sin comentarios.' }}</p>

        @if ($datos['registro']['comentarios_rechazo'])
            <h2 style="margin:20px 0 8px;font-size:16px">Comentarios de resolución o motivo de rechazo</h2>
            <p style="margin:0;white-space:pre-wrap">{{ $datos['registro']['comentarios_rechazo'] }}</p>
        @endif

        <h2 style="margin:20px 0 8px;font-size:16px">Evidencias adjuntas</h2>
        @if (count($datos['registro']['evidencias']))
            <ul style="margin:0;padding-left:24px">
                @foreach ($datos['registro']['evidencias'] as $evidencia)
                    <li>{{ $evidencia['nombre'] }} ({{ $evidencia['mime_type'] }}, .{{ $evidencia['extension'] }})</li>
                @endforeach
            </ul>
        @else
            <p style="margin:0">Este registro no tiene evidencias adjuntas.</p>
        @endif
    </main>
</body>
</html>
