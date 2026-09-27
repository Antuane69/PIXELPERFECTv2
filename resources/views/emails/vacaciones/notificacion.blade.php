@extends('emails.layouts.base', [
    'title' => $tipo === 'solicitud' ? 'Solicitud de vacaciones' : 'Resolución de vacaciones',
    'preheader' => $tipo === 'solicitud' ? 'Una solicitud de vacaciones espera revisión.' : 'La solicitud de vacaciones recibió una respuesta.',
    'footer' => 'Mensaje enviado desde '.config('app.name').'.',
])

@section('content')
    <div style="display: inline-block; padding: 7px 12px; border-radius: 999px; background-color: #f3e8ff; color: #7e22ce; font-size: 12px; font-weight: bold; letter-spacing: 1.3px; text-transform: uppercase;">
        Vacaciones | {{ $datos['empresa'] }}
    </div>

    <h1 style="margin: 18px 0 10px; color: #3f2448; font-family: Georgia, 'Times New Roman', serif; font-size: 28px; font-weight: normal; line-height: 1.2;">
        {{ $tipo === 'solicitud' ? 'Nueva solicitud de vacaciones' : 'Respuesta a tu solicitud de vacaciones' }}
    </h1>

    <p style="margin: 0 0 22px; color: #66566c; font-size: 15px; line-height: 1.7;">
        {{ $tipo === 'solicitud' ? 'La persona solicitante compartió esta información para que puedas revisar el periodo y sus posibles traslapes.' : 'La solicitud quedó '.mb_strtolower($datos['estado']).'.' }}
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; font-size: 14px; line-height: 1.6;">
        <tr><td style="padding: 9px 0; color: #78657f; width: 42%;">Empleado</td><td style="padding: 9px 0; color: #33253a; font-weight: bold;">{{ $datos['empleado'] }}</td></tr>
        <tr><td style="padding: 9px 0; color: #78657f;">Solicitó</td><td style="padding: 9px 0; color: #33253a;">{{ $datos['solicitante'] }}</td></tr>
        <tr><td style="padding: 9px 0; color: #78657f;">Periodo</td><td style="padding: 9px 0; color: #33253a;">{{ \Carbon\CarbonImmutable::parse($datos['fecha_inicio'])->format('d/m/Y') }} al {{ \Carbon\CarbonImmutable::parse($datos['fecha_fin'])->format('d/m/Y') }}</td></tr>
        <tr><td style="padding: 9px 0; color: #78657f;">Días solicitados</td><td style="padding: 9px 0; color: #33253a;">{{ $datos['dias_solicitados'] }}</td></tr>
        <tr><td style="padding: 9px 0; color: #78657f;">Saldo al solicitar</td><td style="padding: 9px 0; color: #33253a;">{{ $datos['saldo_dias'] }} días</td></tr>
        <tr><td style="padding: 9px 0; color: #78657f;">Últimas vacaciones</td><td style="padding: 9px 0; color: #33253a;">{{ $datos['ultima_vacacion'] ? \Carbon\CarbonImmutable::parse($datos['ultima_vacacion'])->format('d/m/Y') : 'Sin registro anterior' }}</td></tr>
        <tr><td style="padding: 9px 0; color: #78657f;">Cubrirán el turno</td><td style="padding: 9px 0; color: #33253a;">{{ implode(', ', $datos['coberturas']) }}</td></tr>
        @if($datos['comentarios'])
            <tr><td colspan="2" style="padding: 14px 0 4px; color: #78657f;">Comentarios de la solicitud</td></tr>
            <tr><td colspan="2" style="padding: 0 0 12px; color: #33253a; white-space: pre-line;">{{ $datos['comentarios'] }}</td></tr>
        @endif
        @if($tipo === 'resolucion')
            <tr><td style="padding: 9px 0; color: #78657f;">Estado</td><td style="padding: 9px 0; color: #33253a; font-weight: bold;">{{ $datos['estado'] }}</td></tr>
            @if($datos['resuelto_por'])
                <tr><td style="padding: 9px 0; color: #78657f;">Revisó</td><td style="padding: 9px 0; color: #33253a;">{{ $datos['resuelto_por'] }}</td></tr>
            @endif
            @if($datos['comentarios_rechazo'])
                <tr><td colspan="2" style="padding: 14px 0 4px; color: #78657f;">Motivo del rechazo</td></tr>
                <tr><td colspan="2" style="padding: 0 0 12px; color: #33253a; white-space: pre-line;">{{ $datos['comentarios_rechazo'] }}</td></tr>
            @endif
        @endif
    </table>

    @if($tipo === 'solicitud')
        <h2 style="margin: 26px 0 8px; color: #3f2448; font-size: 17px;">Vacaciones próximas que coinciden con estas fechas</h2>
        @if(count($datos['coincidencias']) > 0)
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; font-size: 12px; line-height: 1.5;">
                <thead><tr style="background-color: #f6f1f8;"><th align="left" style="padding: 9px 7px; color: #66566c;">Empleado</th><th align="left" style="padding: 9px 7px; color: #66566c;">Periodo</th><th align="left" style="padding: 9px 7px; color: #66566c;">Días / estado</th></tr></thead>
                <tbody>
                    @foreach($datos['coincidencias'] as $coincidencia)
                        <tr>
                            <td style="border-bottom: 1px solid #eadff0; padding: 9px 7px; color: #33253a;">{{ $coincidencia['empleado'] }}</td>
                            <td style="border-bottom: 1px solid #eadff0; padding: 9px 7px; color: #33253a;">{{ \Carbon\CarbonImmutable::parse($coincidencia['fecha_inicio'])->format('d/m/Y') }} – {{ \Carbon\CarbonImmutable::parse($coincidencia['fecha_fin'])->format('d/m/Y') }}</td>
                            <td style="border-bottom: 1px solid #eadff0; padding: 9px 7px; color: #33253a;">{{ $coincidencia['dias'] }} / {{ $coincidencia['estado'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if(count($datos['coincidencias']) >= 50)
                <p style="margin: 8px 0 0; color: #8b7a91; font-size: 12px;">Se muestran las primeras 50 coincidencias.</p>
            @endif
        @else
            <p style="margin: 0; border-left: 4px solid #c084fc; padding: 12px 15px; background-color: #faf5ff; color: #6b5a71; font-size: 13px; line-height: 1.6;">No se encontraron otras vacaciones pendientes o autorizadas que coincidan con el periodo.</p>
        @endif
    @endif
@endsection
