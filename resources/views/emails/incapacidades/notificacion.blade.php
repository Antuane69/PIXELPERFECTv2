<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Incapacidad</title>
</head>
<body style="margin:0;background:#f4f4f5;color:#18181b;font-family:Arial,sans-serif;line-height:1.5">
    <main style="max-width:680px;margin:24px auto;padding:24px;background:#fff;border:1px solid #e4e4e7;border-radius:12px">
        <p style="margin:0 0 8px;color:#71717a">{{ $datos['empresa'] }}</p>
        <h1 style="margin:0 0 16px;font-size:22px">
            {{ $tipo === 'solicitud' ? 'Nueva solicitud de incapacidad' : 'Resolución de la incapacidad' }}
        </h1>

        <table style="width:100%;border-collapse:collapse">
            <tbody>
                <tr><th scope="row" style="padding:8px;text-align:left">Empleado</th><td style="padding:8px">{{ $datos['empleado'] }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Solicitante</th><td style="padding:8px">{{ $datos['solicitante'] }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Periodo</th><td style="padding:8px">{{ $datos['fecha_inicio'] }} al {{ $datos['fecha_fin'] }}</td></tr>
                <tr><th scope="row" style="padding:8px;text-align:left">Estatus</th><td style="padding:8px">{{ $datos['estado'] }}</td></tr>
            </tbody>
        </table>

        <h2 style="margin:20px 0 8px;font-size:16px">Motivo</h2>
        <p style="margin:0;white-space:pre-wrap">{{ $datos['motivo'] }}</p>

        @if ($datos['nombre_archivo'])
            <p style="margin:20px 0 0">
                Justificante disponible en el módulo de incapacidades: {{ $datos['nombre_archivo'] }}
            </p>
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
    </main>
</body>
</html>
