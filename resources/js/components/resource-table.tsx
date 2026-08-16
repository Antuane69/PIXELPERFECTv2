import { Inbox } from 'lucide-react';
import type { KeyboardEvent, MouseEvent, ReactNode } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

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
    onRowClick?: (item: T) => void;
    getRowAriaLabel?: (item: T) => string;
};

const interactiveSelector =
    'a, button, input, select, textarea, [role="button"], [role="link"], [data-row-click-ignore]';

export function ResourceTable<T>({
    columns,
    data,
    getRowKey,
    emptyTitle = 'Sin registros',
    emptyDescription = 'No hay información para mostrar con los filtros actuales.',
    onRowClick,
    getRowAriaLabel,
}: ResourceTableProps<T>) {
    const shouldIgnoreRowClick = (
        target: EventTarget | null,
        currentTarget: EventTarget | null,
    ): boolean => {
        if (!(target instanceof Element)) {
            return false;
        }

        const interactiveElement = target.closest(interactiveSelector);

        return (
            interactiveElement !== null && interactiveElement !== currentTarget
        );
    };

    const handleRowClick = (event: MouseEvent, item: T): void => {
        if (shouldIgnoreRowClick(event.target, event.currentTarget)) {
            return;
        }

        onRowClick?.(item);
    };

    const handleRowKeyDown = (event: KeyboardEvent, item: T): void => {
        if (
            shouldIgnoreRowClick(event.target, event.currentTarget) ||
            (event.key !== 'Enter' && event.key !== ' ')
        ) {
            return;
        }

        event.preventDefault();
        onRowClick?.(item);
    };

    const interactiveRowProps = (item: T) =>
        onRowClick
            ? {
                  role: 'button' as const,
                  tabIndex: 0,
                  'aria-label': getRowAriaLabel?.(item),
                  onClick: (event: MouseEvent) => handleRowClick(event, item),
                  onKeyDown: (event: KeyboardEvent) =>
                      handleRowKeyDown(event, item),
              }
            : {};

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
                            <TableRow
                                key={getRowKey(item)}
                                className={cn(
                                    onRowClick &&
                                        'cursor-pointer focus-visible:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                )}
                                {...interactiveRowProps(item)}
                            >
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
                        className={cn(
                            'grid gap-3 rounded-xl border border-border bg-card p-4 shadow-sm',
                            onRowClick &&
                                'cursor-pointer transition-colors hover:bg-muted/35 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        )}
                        {...interactiveRowProps(item)}
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
