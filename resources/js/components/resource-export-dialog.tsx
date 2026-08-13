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
import { exportar } from '@/routes/reportes';

type FilterValue = string | number | boolean | null | undefined;

type ResourceExportDialogProps = {
    report: string;
    filters?: Record<string, FilterValue>;
};

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
    const activeFilters = Object.entries(filters).filter(
        ([, value]) => value !== null && value !== undefined && value !== '',
    );

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

                <form
                    {...exportar.form(report)}
                    className="grid gap-3"
                    onSubmit={() => setOpen(false)}
                >
                    <input type="hidden" name="_token" value={csrfToken()} />
                    {activeFilters.map(([name, value]) => (
                        <input
                            key={name}
                            type="hidden"
                            name={`filtros[${name}]`}
                            value={
                                typeof value === 'boolean'
                                    ? value
                                        ? '1'
                                        : '0'
                                    : String(value)
                            }
                        />
                    ))}

                    <Button
                        type="submit"
                        name="formato"
                        value="pdf"
                        variant="outline"
                        className="h-auto justify-start gap-3 px-4 py-3 text-left"
                    >
                        <FileText className="size-5 text-red-600" />
                        <span className="grid gap-0.5">
                            <span>PDF</span>
                            <span className="text-xs font-normal text-muted-foreground">
                                Documento listo para imprimir
                            </span>
                        </span>
                    </Button>
                    <Button
                        type="submit"
                        name="formato"
                        value="xlsx"
                        variant="outline"
                        className="h-auto justify-start gap-3 px-4 py-3 text-left"
                    >
                        <FileSpreadsheet className="size-5 text-emerald-600" />
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
                </form>
            </DialogContent>
        </Dialog>
    );
}
