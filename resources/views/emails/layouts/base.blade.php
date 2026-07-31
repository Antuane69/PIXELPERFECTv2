<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body style="margin: 0; background-color: #f8f3fa; color: #33253a; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f8f3fa; padding: 32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 680px; background-color: #ffffff; border: 1px solid #eadff0; border-radius: 16px; overflow: hidden;">
                    <tr>
                        <td style="background-color: #6e3f7b; padding: 32px 36px; text-align: center;">
                            <div style="color: #ead2f0; font-size: 12px; letter-spacing: 3px; text-transform: uppercase;">Pixel Perfect</div>
                            <div style="margin-top: 6px; color: #ffffff; font-family: Georgia, serif; font-size: 30px;">Tu espacio de administración</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 36px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top: 1px solid #eadff0; padding: 20px 36px; color: #78657f; font-size: 12px; line-height: 1.6; text-align: center;">
                            {{ $footer ?? 'Mensaje enviado desde '.config('app.name').'.' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
