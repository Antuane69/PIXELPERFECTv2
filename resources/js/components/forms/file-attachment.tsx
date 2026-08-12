import { Download, FileText, FileUp, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import { isImageFile, validateFile } from '@/components/forms/form-utils';
import { ImagePreview } from '@/components/forms/image-preview';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type FileAttachmentProps = {
    id: string;
    name: string;
    accept: string;
    acceptedFormats: string[];
    maxSizeBytes?: number;
    required?: boolean;
    showInputIcon?: boolean;
    onFileChange?: (file: File | null) => void;
};

export function FileAttachment({
    id,
    name,
    accept,
    acceptedFormats,
    maxSizeBytes,
    required = false,
    showInputIcon = true,
    onFileChange,
}: FileAttachmentProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const previewUrlRef = useRef<string | null>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [validationError, setValidationError] = useState<string | null>(null);

    useEffect(() => {
        return () => {
            if (previewUrlRef.current) {
                URL.revokeObjectURL(previewUrlRef.current);
            }
        };
    }, []);

    const updatePreview = (file: File | null): void => {
        if (previewUrlRef.current) {
            URL.revokeObjectURL(previewUrlRef.current);
        }

        const objectUrl =
            file && isImageFile(file) ? URL.createObjectURL(file) : null;

        previewUrlRef.current = objectUrl;
        setPreviewUrl(objectUrl);
    };

    const resetInput = (): void => {
        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    const clearAttachment = (): void => {
        setSelectedFile(null);
        updatePreview(null);
        setValidationError(null);
        resetInput();
        onFileChange?.(null);
    };

    const handleChange = (event: ChangeEvent<HTMLInputElement>): void => {
        const file = event.currentTarget.files?.[0];

        if (!file) {
            return;
        }

        const error = validateFile(file, acceptedFormats, maxSizeBytes);

        if (error) {
            setSelectedFile(null);
            updatePreview(null);
            setValidationError(error);
            resetInput();
            onFileChange?.(null);

            return;
        }

        setSelectedFile(file);
        updatePreview(file);
        setValidationError(null);
        onFileChange?.(file);
    };

    const downloadAttachment = (): void => {
        if (!selectedFile) {
            return;
        }

        const objectUrl = URL.createObjectURL(selectedFile);
        const link = document.createElement('a');

        link.href = objectUrl;
        link.download = selectedFile.name;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
    };

    return (
        <div className="grid gap-2">
            <div className="relative">
                {showInputIcon ? (
                    <FileUp className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                ) : null}
                <Input
                    ref={inputRef}
                    id={id}
                    type="file"
                    name={name}
                    accept={accept}
                    required={required}
                    aria-invalid={Boolean(validationError)}
                    className={showInputIcon ? 'pl-9' : undefined}
                    onChange={handleChange}
                />
            </div>
            {validationError ? (
                <p className="text-sm text-destructive" role="alert">
                    {validationError}
                </p>
            ) : null}
            {selectedFile ? (
                <div className="flex items-center justify-between gap-3 rounded-md border bg-muted/40 p-2">
                    <div className="flex min-w-0 items-center gap-2">
                        {previewUrl ? (
                            <ImagePreview
                                src={previewUrl}
                                active={Boolean(previewUrl)}
                            />
                        ) : (
                            <span className="flex size-12 shrink-0 items-center justify-center rounded-md bg-background text-muted-foreground">
                                <FileText className="size-5" />
                            </span>
                        )}
                        {previewUrl ? (
                            <span
                                className="truncate text-sm"
                                title={selectedFile.name}
                            >
                                {selectedFile.name}
                            </span>
                        ) : (
                            <button
                                type="button"
                                className="inline-flex min-w-0 items-center gap-2 truncate text-left text-sm font-medium text-primary hover:underline"
                                onClick={downloadAttachment}
                                title="Descargar archivo adjunto"
                            >
                                <Download className="size-4 shrink-0" />
                                <span className="truncate">
                                    {selectedFile.name}
                                </span>
                            </button>
                        )}
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Quitar archivo adjunto"
                        onClick={clearAttachment}
                    >
                        <X />
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
