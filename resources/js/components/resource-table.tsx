import { Inbox } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type ResourceColumn<T> = {
    key: string;
    header: string;
    cell: (item: T) => ReactNode;
    className?: string;
    mobileHidden?: boolean;
};

type ResourceTableProps<T> = {
    columns: ResourceColumn<T>[];
    data: T[];
    getRowKey: (item: T) => string | number;
    emptyTitle?: string;
    emptyDescription?: string;
};

export function ResourceTable<T>({
    columns,
    data,
    getRowKey,
    emptyTitle = 'Sin registros',
    emptyDescription = 'No hay información para mostrar con los filtros actuales.',
}: ResourceTableProps<T>) {
    if (!data.length) {
        return (
            <div className="flex min-h-56 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border bg-card p-8 text-center">
                <div className="flex size-11 items-center justify-center rounded-full bg-accent text-accent-foreground">
                    <Inbox className="size-5" />
                </div>
                <div className="grid gap-1">
                    <p className="font-medium">{emptyTitle}</p>
                    <p className="max-w-md text-sm text-muted-foreground">
                        {emptyDescription}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <>
            <div className="hidden overflow-hidden rounded-xl border border-border bg-card shadow-sm md:block">
                <Table>
                    <TableHeader className="bg-muted/55">
                        <TableRow>
                            {columns.map((column) => (
                                <TableHead
                                    key={column.key}
                                    className={column.className}
                                >
                                    {column.header}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {data.map((item) => (
                            <TableRow key={getRowKey(item)}>
                                {columns.map((column) => (
                                    <TableCell
                                        key={column.key}
                                        className={column.className}
                                    >
                                        {column.cell(item)}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <div className="grid gap-3 md:hidden">
                {data.map((item) => (
                    <article
                        key={getRowKey(item)}
                        className="grid gap-3 rounded-xl border border-border bg-card p-4 shadow-sm"
                    >
                        {columns
                            .filter((column) => !column.mobileHidden)
                            .map((column) => (
                                <div
                                    key={column.key}
                                    className="grid grid-cols-[minmax(7rem,0.42fr)_1fr] items-start gap-3 text-sm"
                                >
                                    <span className="font-medium text-muted-foreground">
                                        {column.header}
                                    </span>
                                    <div className="min-w-0 text-right break-words">
                                        {column.cell(item)}
                                    </div>
                                </div>
                            ))}
                    </article>
                ))}
            </div>
        </>
    );
}
