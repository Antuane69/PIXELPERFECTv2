<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

final class ImageCompressor
{
    /**
     * @return array{contents: string, mime_type: string, extension: string}|null
     */
    public function compressIfImage(UploadedFile $file, bool $enforceSourcePixelLimit = true): ?array
    {
        $mimeType = $file->getMimeType();

        if (! is_string($mimeType) || ! str_starts_with($mimeType, 'image/')) {
            return null;
        }

        return $this->compressContentsIfImage($file->getContent(), $mimeType, $enforceSourcePixelLimit);
    }

    /**
     * @return array{contents: string, mime_type: string, extension: string}|null
     */
    public function compressContentsIfImage(
        string $contents,
        string $mimeType,
        bool $enforceSourcePixelLimit = true,
    ): ?array {
        if (! str_starts_with($mimeType, 'image/')) {
            return null;
        }

        if ($contents === '') {
            throw new RuntimeException('No se pudo leer la imagen subida.');
        }

        if ($mimeType === 'image/svg+xml') {
            return [
                'contents' => $this->compressSvg($contents),
                'mime_type' => $mimeType,
                'extension' => 'svg',
            ];
        }

        $imageInfo = @getimagesizefromstring($contents);

        if (! is_array($imageInfo) || ! str_starts_with($imageInfo['mime'], 'image/')) {
            throw new RuntimeException('La imagen subida no pudo ser procesada.');
        }

        $sourceMimeType = $imageInfo['mime'];

        if ($enforceSourcePixelLimit && ! $this->isWithinConfiguredLimits($contents, $sourceMimeType)) {
            throw new RuntimeException('La imagen no es válida o supera el límite de píxeles permitido.');
        }

        $source = @imagecreatefromstring($contents);

        if (! $source instanceof \GdImage) {
            throw new RuntimeException('La imagen subida no pudo ser procesada.');
        }

        $outputMimeType = $this->outputMimeType($sourceMimeType);
        $image = $this->resizeIfNeeded($source, $outputMimeType);

        try {
            $compressedContents = $this->encode($image, $outputMimeType);
        } finally {
            imagedestroy($image);
        }

        return [
            'contents' => $compressedContents,
            'mime_type' => $outputMimeType,
            'extension' => $this->extensionForMimeType($outputMimeType),
        ];
    }

    public function isWithinConfiguredLimits(string $contents, string $mimeType): bool
    {
        $imageInfo = @getimagesizefromstring($contents);

        if (! is_array($imageInfo)
            || ! str_starts_with($imageInfo['mime'], 'image/')
            || ! str_starts_with($mimeType, 'image/')
            || $imageInfo[0] < 1
            || $imageInfo[1] < 1) {
            return false;
        }

        $maxSourcePixels = max((int) config('media.images.max_source_pixels', 25000000), 1);

        return $imageInfo[0] <= intdiv($maxSourcePixels, $imageInfo[1]);
    }

    private function resizeIfNeeded(\GdImage $source, string $mimeType): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $maxDimension = max((int) config('media.images.max_dimension', 2400), 1);

        if (max($width, $height) <= $maxDimension) {
            return $source;
        }

        $scale = $maxDimension / max($width, $height);
        $targetWidth = max((int) round($width * $scale), 1);
        $targetHeight = max((int) round($height * $scale), 1);
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $resized instanceof \GdImage) {
            throw new RuntimeException('No se pudo preparar la imagen comprimida.');
        }

        $this->prepareCanvas($resized, $mimeType);

        if (! imagecopyresampled(
            $resized,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height,
        )) {
            imagedestroy($resized);

            throw new RuntimeException('No se pudo redimensionar la imagen subida.');
        }

        imagedestroy($source);

        return $resized;
    }

    private function prepareCanvas(\GdImage $canvas, string $mimeType): void
    {
        if (! in_array($mimeType, ['image/png', 'image/webp', 'image/gif'], true)) {
            return;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);

        if ($transparent !== false) {
            imagefilledrectangle(
                $canvas,
                0,
                0,
                imagesx($canvas),
                imagesy($canvas),
                $transparent,
            );
        }
    }

    private function encode(\GdImage $image, string $mimeType): string
    {
        ob_start();

        try {
            $encoded = match ($mimeType) {
                'image/jpeg' => imagejpeg(
                    $image,
                    null,
                    $this->boundedConfigValue('media.images.jpeg_quality', 82, 0, 100),
                ),
                'image/png' => imagepng(
                    $image,
                    null,
                    $this->boundedConfigValue('media.images.png_compression', 6, 0, 9),
                ),
                'image/webp' => function_exists('imagewebp')
                    ? imagewebp(
                        $image,
                        null,
                        $this->boundedConfigValue('media.images.webp_quality', 82, 0, 100),
                    )
                    : false,
                'image/gif' => function_exists('imagegif')
                    ? imagegif($image)
                    : false,
                'image/bmp' => function_exists('imagebmp')
                    ? imagebmp($image)
                    : false,
                'image/avif' => function_exists('imageavif')
                    ? imageavif(
                        $image,
                        null,
                        $this->boundedConfigValue('media.images.avif_quality', 82, 0, 100),
                    )
                    : false,
                default => false,
            };
            $contents = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        if ($encoded !== true || ! is_string($contents) || $contents === '') {
            throw new RuntimeException('No se pudo comprimir la imagen subida.');
        }

        return $contents;
    }

    private function boundedConfigValue(
        string $key,
        int $default,
        int $minimum,
        int $maximum,
    ): int {
        return min(max((int) config($key, $default), $minimum), $maximum);
    }

    private function extensionForMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/avif' => 'avif',
            'image/svg+xml' => 'svg',
            default => 'png',
        };
    }

    private function outputMimeType(string $sourceMimeType): string
    {
        $encoderExists = match ($sourceMimeType) {
            'image/jpeg' => function_exists('imagejpeg'),
            'image/png' => function_exists('imagepng'),
            'image/webp' => function_exists('imagewebp'),
            'image/gif' => function_exists('imagegif'),
            'image/bmp' => function_exists('imagebmp'),
            'image/avif' => function_exists('imageavif'),
            default => false,
        };

        return $encoderExists ? $sourceMimeType : 'image/png';
    }

    private function compressSvg(string $contents): string
    {
        if (! class_exists(\DOMDocument::class)) {
            throw new RuntimeException('No se pudo optimizar el archivo SVG.');
        }

        $document = new \DOMDocument;
        $document->preserveWhiteSpace = false;
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOBLANKS);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }

        if (! $loaded
            || $document->documentElement === null
            || strtolower($document->documentElement->localName) !== 'svg') {
            throw new RuntimeException('El archivo SVG no es válido.');
        }

        $xpath = new \DOMXPath($document);
        $comments = $xpath->query('//comment()');

        if ($comments !== false) {
            foreach ($comments as $comment) {
                if (! $comment instanceof \DOMNode) {
                    continue;
                }

                $parent = $comment->parentNode;

                if ($parent instanceof \DOMNode) {
                    $parent->removeChild($comment);
                }
            }
        }

        $compressedContents = $document->saveXML($document->documentElement);

        if (! is_string($compressedContents) || $compressedContents === '') {
            throw new RuntimeException('No se pudo optimizar el archivo SVG.');
        }

        return $compressedContents;
    }
}
