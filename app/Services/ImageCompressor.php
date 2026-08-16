<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

final class ImageCompressor
{
    private const SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * @return array{contents: string, mime_type: string, extension: string}|null
     */
    public function compressIfImage(UploadedFile $file): ?array
    {
        $mimeType = $file->getMimeType();

        if (! is_string($mimeType) || ! in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)) {
            return null;
        }

        $contents = $file->getContent();

        if ($contents === '') {
            throw new RuntimeException('No se pudo leer la imagen subida.');
        }

        $source = @imagecreatefromstring($contents);

        if (! $source instanceof \GdImage) {
            throw new RuntimeException('La imagen subida no pudo ser procesada.');
        }

        $image = $this->resizeIfNeeded($source, $mimeType);

        try {
            $compressedContents = $this->encode($image, $mimeType);
        } finally {
            imagedestroy($image);
        }

        return [
            'contents' => $compressedContents,
            'mime_type' => $mimeType,
            'extension' => $this->extensionForMimeType($mimeType),
        ];
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
        if (! in_array($mimeType, ['image/png', 'image/webp'], true)) {
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
            default => throw new RuntimeException('Tipo de imagen no soportado.'),
        };
    }
}
