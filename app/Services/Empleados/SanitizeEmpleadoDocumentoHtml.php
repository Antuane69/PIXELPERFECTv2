<?php

namespace App\Services\Empleados;

use App\Services\ImageCompressor;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SanitizeEmpleadoDocumentoHtml
{
    private const ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'caption', 'code', 'div', 'em', 'figcaption', 'figure',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 's',
        'span', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr',
        'u', 'ul',
    ];

    private const REMOVE_WITH_CONTENT = [
        'audio', 'button', 'embed', 'form', 'iframe', 'input', 'link', 'math', 'meta', 'object',
        'script', 'source', 'style', 'svg', 'video',
    ];

    public function __construct(private readonly ImageCompressor $imageCompressor) {}

    public function handle(string $html, bool $compressImages = true): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>',
                LIBXML_NONET | LIBXML_HTML_NODEFDTD,
            );
            $body = $document->getElementsByTagName('body')->item(0);

            if ($body === null) {
                return '';
            }

            $this->sanitizeChildren($body, $compressImages);

            $sanitized = '';

            foreach ($body->childNodes as $child) {
                $sanitized .= $document->saveHTML($child);
            }

            return $sanitized;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    private function sanitizeChildren(DOMNode $parent, bool $compressImages): void
    {
        $children = [];

        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || ! $child instanceof DOMElement) {
                if ($child->nodeType === XML_COMMENT_NODE) {
                    $parent->removeChild($child);
                }

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::REMOVE_WITH_CONTENT, true)) {
                $parent->removeChild($child);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->sanitizeChildren($child, $compressImages);

                while ($child->firstChild !== null) {
                    $parent->insertBefore($child->firstChild, $child);
                }

                $parent->removeChild($child);

                continue;
            }

            $this->sanitizeAttributes($child, $tag, $compressImages);
            $this->sanitizeChildren($child, $compressImages);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag, bool $compressImages): void
    {
        $allowed = match ($tag) {
            'a' => ['href', 'style', 'class', 'title'],
            'img' => ['src', 'alt', 'width', 'height', 'style', 'class', 'title'],
            'ol' => ['start', 'type', 'style', 'class'],
            'li' => ['value', 'style', 'class'],
            'td', 'th' => ['colspan', 'rowspan', 'width', 'height', 'align', 'valign', 'style', 'class'],
            'table' => ['width', 'height', 'border', 'cellpadding', 'cellspacing', 'align', 'style', 'class'],
            default => ['align', 'dir', 'lang', 'title', 'style', 'class'],
        };

        $attributes = [];

        foreach ($element->attributes as $attribute) {
            $attributes[] = [$attribute->name, $attribute->value];
        }

        foreach ($attributes as [$name, $value]) {
            if (! in_array(strtolower($name), $allowed, true)) {
                $element->removeAttribute($name);

                continue;
            }

            $sanitizedValue = match (strtolower($name)) {
                'href' => $this->safeLink($value) ? $value : null,
                'src' => $this->sanitizeImage($value, $compressImages),
                'style' => $this->safeStyle($value),
                'class' => preg_match('/^[a-zA-Z0-9 _-]{0,160}$/', $value) === 1 ? $value : null,
                'dir' => in_array(strtolower($value), ['ltr', 'rtl', 'auto'], true) ? $value : null,
                'lang' => preg_match('/^[a-zA-Z0-9-]{1,35}$/', $value) === 1 ? $value : null,
                'width', 'height', 'colspan', 'rowspan', 'start', 'value', 'border', 'cellpadding', 'cellspacing' => preg_match('/^\d{1,4}%?$/', $value) === 1 ? $value : null,
                default => $value,
            };

            if ($sanitizedValue === null) {
                $element->removeAttribute($name);

                continue;
            }

            $element->setAttribute($name, $sanitizedValue);
        }
    }

    private function safeLink(string $url): bool
    {
        return preg_match('/^(https?:\/\/|mailto:|tel:|#)/i', trim($url)) === 1;
    }

    private function sanitizeImage(string $source, bool $compressImage): ?string
    {
        if (preg_match('/^data:(image\/(?:png|jpe?g|webp|gif));base64,([a-zA-Z0-9+\/=\r\n]+)$/i', $source, $matches) !== 1) {
            return null;
        }

        $encodedContents = str_replace(["\r", "\n"], '', $matches[2]);

        if (strlen($encodedContents) > 700_000) {
            return null;
        }

        $contents = base64_decode($encodedContents, true);
        $mimeType = strtolower($matches[1]);

        if ($contents === false) {
            return null;
        }

        if (! $this->imageCompressor->isWithinConfiguredLimits($contents, $mimeType)) {
            if ($compressImage) {
                throw ValidationException::withMessages([
                    'contenido_html' => 'Una imagen del documento no es válida o supera el límite de píxeles permitido.',
                ]);
            }

            return null;
        }

        if (! $compressImage) {
            return 'data:'.$mimeType.';base64,'.base64_encode($contents);
        }

        try {
            $compressed = $this->imageCompressor->compressContentsIfImage(
                $contents,
                $mimeType,
                enforceSourcePixelLimit: true,
            );
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'contenido_html' => 'No se pudo procesar una imagen del documento. Usa JPEG, PNG o WebP.',
            ]);
        }

        if ($compressed === null) {
            throw ValidationException::withMessages([
                'contenido_html' => 'Las imágenes del documento deben usar formato JPEG, PNG o WebP.',
            ]);
        }

        return 'data:'.$compressed['mime_type'].';base64,'.base64_encode($compressed['contents']);
    }

    private function safeStyle(string $style): ?string
    {
        $allowedProperties = '/^(background-color|border(?:-[a-z-]+)?|color|display|float|font(?:-[a-z-]+)?|height|letter-spacing|line-height|list-style(?:-[a-z-]+)?|margin(?:-[a-z-]+)?|padding(?:-[a-z-]+)?|page-break-(?:after|before|inside)|text-(?:align|decoration|indent|transform)|vertical-align|white-space|width)$/i';
        $safeDeclarations = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));

            if (preg_match($allowedProperties, $property) !== 1
                || preg_match('/url\s*\(|expression\s*\(|@import|javascript:|vbscript:|behavior\s*:|binding\s*:/i', $value) === 1) {
                continue;
            }

            $safeDeclarations[] = strtolower($property).': '.$value;
        }

        return $safeDeclarations === [] ? null : implode('; ', $safeDeclarations);
    }
}
