<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body style="margin: 0; background-color: #f6f1f8; color: #33253a; font-family: Arial, Helvetica, sans-serif;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; color: transparent;">
        {{ $preheader ?? $title ?? config('app.name') }}
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f6f1f8; padding: 36px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 640px; background-color: #ffffff; border: 1px solid #e7dcea; border-radius: 20px; box-shadow: 0 16px 40px rgba(78, 45, 87, 0.10); overflow: hidden;">
                    <tr>
                        <td style="background-color: #3f2448; border-bottom: 5px solid #a855f7; padding: 30px 36px; text-align: center;">
                            <div style="font-size: 25px; line-height: 1; letter-spacing: -1.5px; white-space: nowrap;">
                                <span style="color: #ffffff; font-weight: 900;">PIXEL</span><span style="color: #d8b4fe; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-weight: 600;">PERFECT</span>
                            </div>
                            <div style="margin-top: 10px; color: #e9d5ff; font-size: 12px; letter-spacing: 1.8px; text-transform: uppercase;">Panel administrativo</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 38px 40px 34px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top: 1px solid #eadff0; background-color: #fcf9fd; padding: 22px 36px; color: #78657f; font-size: 12px; line-height: 1.7; text-align: center;">
                            {{ $footer ?? 'Mensaje enviado desde '.config('app.name').'.' }}
                        </td>
                    </tr>
                </table>

                <p style="margin: 18px 0 0; color: #9b8ca0; font-size: 11px; line-height: 1.5; text-align: center;">
                    © {{ now()->year }} {{ config('app.name') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
