<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: letter;
            margin: 28mm 22mm 24mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #271b2c;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.5;
        }

        .document-frame {
            border: 1px solid #e6d8ec;
            border-radius: 7px;
            border-top: 3px solid #935aac;
            bottom: 6mm;
            left: 6mm;
            position: fixed;
            right: 6mm;
            top: 6mm;
        }

        .document-header {
            border-bottom: 1px solid #e6d8ec;
            left: 0;
            padding-bottom: 7px;
            position: fixed;
            right: 0;
            top: -20mm;
        }

        .document-header-table,
        .document-footer-table {
            border-collapse: collapse;
            width: 100%;
        }

        .document-header-table td,
        .document-footer-table td {
            border: 0;
            padding: 0;
            vertical-align: middle;
        }

        .document-brand-mark {
            background-color: #935aac;
            border-radius: 5px;
            color: #ffffff;
            display: inline-block;
            font-size: 8pt;
            font-weight: bold;
            height: 27px;
            line-height: 27px;
            text-align: center;
            width: 27px;
        }

        .document-brand-copy {
            padding-left: 8px !important;
        }

        .document-brand-copy strong,
        .document-brand-copy span,
        .document-title-block span,
        .document-title-block strong {
            display: block;
        }

        .document-brand-copy strong {
            color: #271b2c;
            font-size: 8pt;
            letter-spacing: 0.5px;
        }

        .document-brand-copy span {
            color: #6f5978;
            font-size: 7pt;
            margin-top: 2px;
        }

        .document-title-block {
            text-align: right;
            width: 48%;
        }

        .document-title-block .document-eyebrow {
            color: #935aac;
            font-size: 6.5pt;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        .document-title-block .document-name {
            color: #6f5978;
            font-size: 8pt;
            word-wrap: break-word;
        }

        .document-content {
            position: relative;
        }

        .document-content,
        .document-content * {
            max-width: 100% !important;
        }

        .document-content > :first-child {
            margin-top: 0 !important;
        }

        .document-content h1,
        .document-content h2,
        .document-content h3,
        .document-content h4,
        .document-content h5,
        .document-content h6 {
            color: #271b2c;
            page-break-after: avoid;
        }

        .document-content h1 {
            border-bottom: 1px solid #e6d8ec;
            color: #935aac;
            font-size: 19pt;
            line-height: 1.25;
            margin: 0 0 12pt;
            padding-bottom: 6pt;
        }

        .document-content h2 {
            color: #7e4896;
            font-size: 14pt;
            margin: 16pt 0 7pt;
        }

        .document-content h3 {
            color: #6f5978;
            font-size: 11pt;
            margin: 12pt 0 6pt;
        }

        .document-content p {
            margin: 0 0 9pt;
        }

        .document-content a {
            color: #7e4896;
        }

        .document-content blockquote {
            background-color: #fcf9fe;
            border-left: 3px solid #935aac;
            color: #6f5978;
            margin: 10pt 0;
            padding: 8pt 10pt;
        }

        .document-content table {
            border-collapse: collapse;
            margin: 10pt 0 12pt;
            table-layout: auto;
            width: 100%;
        }

        .document-content thead {
            display: table-header-group;
        }

        .document-content tr {
            page-break-inside: avoid;
        }

        .document-content th,
        .document-content td {
            border: 1px solid #e6d8ec;
            padding: 6pt;
            vertical-align: top;
        }

        .document-content th {
            background-color: #935aac;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
        }

        .document-content img {
            height: auto !important;
            max-width: 100% !important;
        }

        .document-content ul,
        .document-content ol {
            margin: 0 0 9pt;
            padding-left: 20pt;
        }

        .document-content pre {
            background-color: #fcf9fe;
            border: 1px solid #e6d8ec;
            padding: 8pt;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .document-content hr {
            border: 0;
            border-top: 1px solid #e6d8ec;
            margin: 14pt 0;
        }

        .document-footer {
            border-top: 1px solid #e6d8ec;
            bottom: -16mm;
            color: #6f5978;
            font-size: 7pt;
            left: 0;
            padding-top: 6px;
            position: fixed;
            right: 0;
        }

        .document-page-number {
            text-align: right;
        }

        .document-page-number::after {
            content: counter(page);
            color: #935aac;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="document-frame"></div>

    <header class="document-header">
        <table class="document-header-table">
            <tbody>
                <tr>
                    <td style="width: 30px">
                        <span class="document-brand-mark">PP</span>
                    </td>
                    <td class="document-brand-copy">
                        <strong>PIXEL PERFECT</strong>
                        <span>Gestión empresarial</span>
                    </td>
                    <td class="document-title-block">
                        <span class="document-eyebrow">Documento digital</span>
                        <strong class="document-name">{{ $nombreDocumento }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>
    </header>

    <main class="document-content">
        {!! $contenidoHtml !!}
    </main>

    <footer class="document-footer">
        <table class="document-footer-table">
            <tbody>
                <tr>
                    <td>Documento generado con Pixel Perfect</td>
                    <td class="document-page-number">Página </td>
                </tr>
            </tbody>
        </table>
    </footer>
</body>
</html>
