@extends('emails.layouts.base', [
    'title' => 'Confirma tu correo electrónico',
    'footer' => 'Este es un mensaje automático de seguridad de '.config('app.name').'.',
])

@section('content')
    <div style="display: inline-block; padding: 7px 12px; border-radius: 999px; background-color: #f0e5f4; color: #6e3f7b; font-size: 12px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase;">
        Seguridad de tu cuenta
    </div>

    <h1 style="margin: 18px 0 8px; color: #4d2d57; font-family: Georgia, serif; font-size: 30px; font-weight: normal; line-height: 1.2;">
        Hola, {{ $name }}
    </h1>

    <p style="margin: 0 0 24px; color: #66566c; font-size: 15px; line-height: 1.7;">
        Confirma tu correo electrónico para activar tu acceso al panel de Pixel Perfect.
    </p>

    <div style="text-align: center;">
        <a href="{{ $verificationUrl }}" style="display: inline-block; border-radius: 8px; padding: 13px 24px; background-color: #9f67b4; color: #ffffff; font-size: 15px; font-weight: bold; text-decoration: none;">
            Confirmar mi correo
        </a>
    </div>

    <p style="margin: 28px 0 0; border-left: 4px solid #c49bd0; padding: 12px 16px; background-color: #fbf7fc; color: #78657f; font-size: 13px; line-height: 1.6;">
        Este enlace vence en {{ $expirationMinutes }} minutos. Si no solicitaste este acceso, puedes ignorar este mensaje.
    </p>
@endsection
