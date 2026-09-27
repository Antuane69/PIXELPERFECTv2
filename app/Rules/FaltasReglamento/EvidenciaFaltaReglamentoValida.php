<?php

namespace App\Rules\FaltasReglamento;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class EvidenciaFaltaReglamentoValida implements ValidationRule
{
    /** @param Closure(string, ?string=): mixed $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('Selecciona un archivo válido.');

            return;
        }

        $mimeType = strtolower((string) $value->getMimeType());
        $extension = strtolower($value->getClientOriginalExtension());
        $isImage = str_starts_with($mimeType, 'image/') && preg_match('/\A[a-z0-9]{1,20}\z/', $extension) === 1;
        $isPdf = $extension === 'pdf' && $mimeType === 'application/pdf';
        $isDoc = $extension === 'doc' && $this->isLegacyWordDocument($value, $mimeType);
        $isDocx = $extension === 'docx' && $this->isWordOpenXmlDocument($value, $mimeType);

        if (! $isImage && ! $isPdf && ! $isDoc && ! $isDocx) {
            $fail('Adjunta un PDF, documento Word o archivo de imagen válido.');
        }
    }

    private function isLegacyWordDocument(UploadedFile $file, string $mimeType): bool
    {
        if (in_array($mimeType, ['application/msword', 'application/x-ole-storage', 'application/vnd.ms-office'], true)) {
            return true;
        }

        $contents = @file_get_contents($file->getRealPath());

        return $mimeType === 'application/octet-stream'
            && is_string($contents)
            && str_starts_with($contents, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
    }

    private function isWordOpenXmlDocument(UploadedFile $file, string $mimeType): bool
    {
        if ($mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return true;
        }

        if (! in_array($mimeType, ['application/zip', 'application/octet-stream'], true)) {
            return false;
        }

        $archive = new ZipArchive;
        $path = $file->getRealPath();

        if (! is_string($path) || $archive->open($path) !== true) {
            return false;
        }

        try {
            return $archive->locateName('[Content_Types].xml') !== false
                && $archive->locateName('word/document.xml') !== false;
        } finally {
            $archive->close();
        }
    }
}
