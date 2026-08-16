import { Download, FileSpreadsheet, FileText } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { showBackendErrorAlert } from '@/lib/app-alerts';
import { exportar } from '@/routes/reportes';

type FilterValue = string | number | boolean | null | undefined;

type ResourceExportDialogProps = {
    report: string;
    filters?: Record<string, FilterValue>;
};

type ExportFormat = 'pdf' | 'xlsx';

const csrfToken = () =>
    typeof document === 'undefined'
        ? ''
        : (document
              .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
              ?.getAttribute('content') ?? '');

export function ResourceExportDialog({
    report,
    filters = {},
}: ResourceExportDialogProps) {
    const [open, setOpen] = useState(false);
    const [processingFormat, setProcessingFormat] =
        useState<ExportFormat | null>(null);
    const activeFilters = Object.entries(filters).filter(
        ([, value]) => value !== null && value !== undefined && value !== '',
    );

    const handleExport = async (format: ExportFormat): Promise<void> => {
        setProcessingFormat(format);

        try {
            const formData = new FormData();
            formData.append('_token', csrfToken());
            formData.append('formato', format);

            activeFilters.forEach(([name, value]) => {
                formData.append(
                    `filtros[${name}]`,
                    typeof value === 'boolean'
                        ? value
                            ? '1'
                            : '0'
                        : String(value),
                );
            });

            const response = await fetch(exportar.url(report), {
                method: 'POST',
                headers: {
                    Accept: '*/*',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            if (!response.ok) {
                throw new Error(await response.text());
            }

            const blob = await response.blob();
            const downloadUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            const filename = filenameFromResponse(
                response.headers.get('Content-Disposition'),
                report,
                format,
            );

            link.href = downloadUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(downloadUrl);
            setOpen(false);
        } catch (error: unknown) {
            showBackendErrorAlert(error, {
                title: 'No se pudo exportar el reporte',
            });
        } finally {
            setProcessingFormat(null);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="outline">
                    <Download /> Exportar
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Exportar reporte</DialogTitle>
                    <DialogDescription>
                        Selecciona formato. Archivo incluirá todos registros que
                        coincidan con filtros actuales, no solo página visible.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        className="h-auto justify-start gap-3 px-4 py-3 text-left"
                        disabled={processingFormat !== null}
                        onClick={() => void handleExport('pdf')}
                    >
                        {processingFormat === 'pdf' ? (
                            <Spinner />
                        ) : (
                            <FileText className="size-5 text-red-600" />
                        )}
                        <span className="grid gap-0.5">
                            <span>PDF</span>
                            <span className="text-xs font-normal text-muted-foreground">
                                Documento listo para imprimir
                            </span>
                        </span>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        className="h-auto justify-start gap-3 px-4 py-3 text-left"
                        disabled={processingFormat !== null}
                        onClick={() => void handleExport('xlsx')}
                    >
                        {processingFormat === 'xlsx' ? (
                            <Spinner />
                        ) : (
                            <FileSpreadsheet className="size-5 text-emerald-600" />
                        )}
                        <span className="grid gap-0.5">
                            <span>Excel</span>
                            <span className="text-xs font-normal text-muted-foreground">
                                Hoja editable con datos completos
                            </span>
                        </span>
                    </Button>

                    <DialogFooter className="mt-2">
                        <DialogClose asChild>
                            <Button type="button" variant="ghost">
                                Cancelar
                            </Button>
                        </DialogClose>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function filenameFromResponse(
    contentDisposition: string | null,
    report: string,
    format: ExportFormat,
): string {
    const encodedFilename = contentDisposition?.match(
        /filename\*=UTF-8''([^;]+)/i,
    )?.[1];

    if (encodedFilename) {
        return decodeURIComponent(encodedFilename);
    }

    const filename = contentDisposition?.match(/filename="?([^";]+)"?/i)?.[1];

    return filename ?? `reporte-${report}.${format}`;
}
