<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="{{ resource_path('css/pdf-report.css') }}">
</head>
<body>
    <header class="report-header">
        <table class="report-header-table">
            <tr>
                <td class="report-brand">
                    @if ($logoPath && is_file($logoPath))
                        <img src="{{ $logoPath }}" alt="{{ config('app.name') }}">
                    @else
                        <strong>{{ config('app.name') }}</strong>
                    @endif
                </td>
                <td class="report-meta">
                    <span>Generado: {{ $generatedAt }}</span>
                    <span>Total: {{ $totalRows }}</span>
                </td>
            </tr>
        </table>
    </header>

    <h1>{{ $title }}</h1>

    @if ($subtitle)
        <p class="report-subtitle">{{ $subtitle }}</p>
    @endif

    <table @class(['data-table', 'fixed-layout' => $fixedTableLayout])>
        @if (! empty($columnWidths))
            <colgroup>
                @foreach ($columnWidths as $width)
                    <col @style(['width: '.$width => $width])>
                @endforeach
            </colgroup>
        @endif
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cellIndex => $cell)
                        <td>
                            @if (in_array($cellIndex, $htmlColumnIndexes, true))
                                {!! (string) $cell !!}
                            @else
                                {!! nl2br(e((string) $cell)) !!}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="empty-cell" colspan="{{ max(count($headings), 1) }}">Sin registros</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
