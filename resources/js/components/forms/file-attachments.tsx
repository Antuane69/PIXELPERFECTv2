import { FileImage, FileText, X } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import {
    isImageFile,
    MAX_FILE_SIZE_BYTES,
    validateFile,
} from '@/components/forms/form-utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type FileAttachmentsProps = {
    id: string;
    name: string;
    accept: string;
    describedBy?: string;
};

const documentFormats = ['pdf', 'doc', 'docx'];

export function FileAttachments({
    id,
    name,
    accept,
    describedBy,
}: FileAttachmentsProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [files, setFiles] = useState<File[]>([]);
    const [validationError, setValidationError] = useState<string | null>(null);

    const replaceInputFiles = (nextFiles: File[]): void => {
        const input = inputRef.current;

        if (!input) {
            return;
        }

        const transfer = new DataTransfer();
        nextFiles.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
        setFiles(nextFiles);
    };

    const handleChange = (event: ChangeEvent<HTMLInputElement>): void => {
        const selectedFiles = Array.from(event.currentTarget.files ?? []);
        const acceptsAnyImage = accept
            .split(',')
            .some((format) => format.trim() === 'image/*');
        const acceptedFiles: File[] = [];
        let error: string | null = null;

        selectedFiles.forEach((file) => {
            const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
            const isImageCandidate =
                isImageFile(file) ||
                (acceptsAnyImage &&
                    extension !== '' &&
                    !documentFormats.includes(extension));
            const fileError = isImageCandidate
                ? validateFile(file, [extension], MAX_FILE_SIZE_BYTES)
                : validateFile(file, documentFormats, MAX_FILE_SIZE_BYTES);

            if (fileError) {
                error ??= fileError;

                return;
            }

            acceptedFiles.push(file);
        });

        const nextFiles = [...files];
        acceptedFiles.forEach((file) => {
            const duplicate = nextFiles.some(
                (current) =>
                    current.name === file.name &&
                    current.size === file.size &&
                    current.lastModified === file.lastModified,
            );

            if (!duplicate) {
                nextFiles.push(file);
            }
        });

        replaceInputFiles(nextFiles);
        setValidationError(error);
    };

    const removeFile = (file: File): void => {
        replaceInputFiles(files.filter((current) => current !== file));
        setValidationError(null);
    };

    return (
        <div className="grid gap-2">
            <Input
                ref={inputRef}
                id={id}
                name={name}
                type="file"
                accept={accept}
                multiple
                aria-invalid={Boolean(validationError)}
                aria-describedby={describedBy}
                onChange={handleChange}
            />
            <p className="text-xs text-muted-foreground">
                Puedes adjuntar varios archivos. PDF, DOC, DOCX o imágenes;
                máximo 10 MB por archivo.
            </p>
            {validationError ? (
                <p className="text-sm text-destructive" role="alert">
                    {validationError}
                </p>
            ) : null}
            {files.length > 0 ? (
                <ul
                    className="grid gap-2"
                    aria-label="Evidencias seleccionadas"
                >
                    {files.map((file, index) => (
                        <li
                            key={`${file.name}-${file.size}-${file.lastModified}-${index}`}
                            className="flex min-w-0 items-center justify-between gap-3 rounded-md border border-border bg-muted/30 px-3 py-2"
                        >
                            <span className="flex min-w-0 items-center gap-2 text-sm">
                                {isImageFile(file) ? (
                                    <FileImage
                                        aria-hidden="true"
                                        className="size-4 shrink-0 text-muted-foreground"
                                    />
                                ) : (
                                    <FileText
                                        aria-hidden="true"
                                        className="size-4 shrink-0 text-muted-foreground"
                                    />
                                )}
                                <span className="truncate" title={file.name}>
                                    {file.name}
                                </span>
                            </span>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                aria-label={`Quitar ${file.name}`}
                                onClick={() => removeFile(file)}
                            >
                                <X aria-hidden="true" />
                            </Button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
