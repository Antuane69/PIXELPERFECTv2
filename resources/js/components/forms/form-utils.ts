import type { FormEvent } from 'react';

export const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024;

export function normalizeInput(
    event: FormEvent<HTMLInputElement>,
    normalize: (value: string) => string,
): void {
    const currentValue = event.currentTarget.value;
    const normalizedValue = normalize(currentValue);

    if (currentValue !== normalizedValue) {
        event.currentTarget.value = normalizedValue;
    }
}

export function normalizeDigits(value: string, maxLength: number): string {
    return value.replace(/[^0-9]/g, '').slice(0, maxLength);
}

export function normalizeCurp(value: string): string {
    return value
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '')
        .slice(0, 18);
}

export function normalizeRfc(value: string): string {
    return value
        .toUpperCase()
        .replace(/[^A-ZÑ&0-9]/g, '')
        .slice(0, 13);
}

export function normalizeMoney(value: string): string {
    const normalized = value.replace(',', '.').replace(/[^0-9.]/g, '');
    const [whole, ...decimalParts] = normalized.split('.');
    const decimals = decimalParts.join('').slice(0, 2);

    if (decimalParts.length === 0) {
        return whole;
    }

    if (whole === '') {
        return decimals === '' ? '0.' : `0.${decimals}`;
    }

    return `${whole}.${decimals}`;
}

export function documentExtensions(formats: string[]): string[] {
    return formats
        .map((format) => format.trim().toLowerCase().replace(/^\./, ''))
        .filter(Boolean);
}

export function validateFile(
    file: File,
    acceptedFormats: string[],
    maxSizeBytes: number = MAX_FILE_SIZE_BYTES,
): string | null {
    const allowedExtensions = documentExtensions(acceptedFormats);
    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

    if (!allowedExtensions.includes(extension)) {
        return `Archivo no permitido. Usa: ${allowedExtensions
            .map((item) => item.toUpperCase())
            .join(', ')}.`;
    }

    if (file.size > maxSizeBytes) {
        return `El archivo supera el máximo permitido de ${Math.round(
            maxSizeBytes / 1024 / 1024,
        )} MB.`;
    }

    return null;
}

export function isImageFile(file: File): boolean {
    return file.type.startsWith('image/');
}
