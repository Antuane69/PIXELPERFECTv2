import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { LaravelPaginator } from '@/types/domain';

type ResourcePaginationProps = {
    paginator: LaravelPaginator<unknown>;
    onPageChange?: (page: number) => void;
};

function linkLabel(label: string) {
    if (label.includes('Previous') || label.includes('Anterior')) {
        return (
            <>
                <ChevronLeft aria-hidden="true" className="size-4" />
                <span className="sr-only">Página anterior</span>
            </>
        );
    }

    if (label.includes('Next') || label.includes('Siguiente')) {
        return (
            <>
                <ChevronRight aria-hidden="true" className="size-4" />
                <span className="sr-only">Página siguiente</span>
            </>
        );
    }

    return label.replace(/&[^;]+;/g, '').trim();
}

export function ResourcePagination({
    paginator,
    onPageChange,
}: ResourcePaginationProps) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <nav
            aria-label="Paginación"
            className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p className="text-sm text-muted-foreground">
                Mostrando {paginator.from ?? 0}–{paginator.to ?? 0} de{' '}
                {paginator.total}
            </p>
            <div className="flex flex-wrap items-center gap-1">
                {paginator.links.map((link, index) => {
                    const content = linkLabel(link.label);
                    const page = link.page;
                    const className = cn(
                        'inline-flex size-9 items-center justify-center rounded-md border border-input text-sm font-medium transition-colors',
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'bg-background hover:bg-accent hover:text-accent-foreground',
                        (!link.url && !onPageChange) ||
                            (onPageChange && !link.page)
                            ? 'pointer-events-none opacity-45'
                            : undefined,
                    );

                    if (onPageChange) {
                        return typeof page === 'number' ? (
                            <button
                                key={`${link.label}-${index}`}
                                type="button"
                                className={className}
                                aria-current={link.active ? 'page' : undefined}
                                aria-label={
                                    link.label.includes('Previous') ||
                                    link.label.includes('Anterior')
                                        ? 'Página anterior'
                                        : link.label.includes('Next') ||
                                            link.label.includes('Siguiente')
                                          ? 'Página siguiente'
                                          : `Página ${page}`
                                }
                                onClick={() => onPageChange(page)}
                            >
                                {content}
                            </button>
                        ) : (
                            <span
                                key={`${link.label}-${index}`}
                                className={className}
                                aria-disabled="true"
                            >
                                {content}
                            </span>
                        );
                    }

                    return link.url ? (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url}
                            preserveScroll
                            preserveState
                            prefetch
                            className={className}
                            aria-current={link.active ? 'page' : undefined}
                        >
                            {content}
                        </Link>
                    ) : (
                        <span
                            key={`${link.label}-${index}`}
                            className={className}
                            aria-disabled="true"
                        >
                            {content}
                        </span>
                    );
                })}
            </div>
        </nav>
    );
}
