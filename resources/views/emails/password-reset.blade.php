@extends('emails.layouts.base', [
    'title' => 'Restablece tu contraseña',
    'preheader' => 'Usa este enlace seguro para crear una nueva contraseña.',
    'footer' => 'Este es un mensaje automático de seguridad de '.config('app.name').'.',
])

@section('content')
    <div style="display: inline-block; padding: 7px 12px; border-radius: 999px; background-color: #f3e8ff; color: #7e22ce; font-size: 12px; font-weight: bold; letter-spacing: 1.3px; text-transform: uppercase;">
        Recuperación de acceso
    </div>

    <h1 style="margin: 18px 0 10px; color: #3f2448; font-family: Georgia, 'Times New Roman', serif; font-size: 30px; font-weight: normal; line-height: 1.2;">
        Hola, {{ $name }}
    </h1>

    <p style="margin: 0 0 24px; color: #66566c; font-size: 15px; line-height: 1.7;">
        Recibimos una solicitud para restablecer la contraseña de tu cuenta. Usa el siguiente botón para crear una nueva.
    </p>

    <div style="text-align: center;">
        <a href="{{ $resetUrl }}" style="display: inline-block; border-radius: 10px; padding: 14px 26px; background-color: #9333ea; color: #ffffff; font-size: 15px; font-weight: bold; text-decoration: none;">
            Restablecer contraseña
        </a>
    </div>

    <p style="margin: 28px 0 0; border-left: 4px solid #c084fc; padding: 13px 16px; background-color: #faf5ff; color: #6b5a71; font-size: 13px; line-height: 1.6;">
        Este enlace vence en {{ $expirationMinutes }} minutos. Si no solicitaste el cambio, tu contraseña seguirá siendo la misma y puedes ignorar este mensaje.
    </p>

    <p style="margin: 22px 0 6px; color: #8b7a91; font-size: 12px; line-height: 1.6;">
        Si el botón no funciona, copia y pega este enlace en tu navegador:
    </p>
    <p style="margin: 0; word-break: break-all; color: #7e22ce; font-size: 12px; line-height: 1.6;">
        <a href="{{ $resetUrl }}" style="color: #7e22ce; text-decoration: underline;">{{ $resetUrl }}</a>
    </p>
@endsection
