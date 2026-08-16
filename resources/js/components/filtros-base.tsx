import { router } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

type GetRoute = {
    url: string;
};

export type FilterValue = string | number | boolean;
type QueryValue = FilterValue | FilterValue[];
type FilterSelections = Record<string, string[]>;

export type FilterFacetOption = {
    value: FilterValue;
    label: string;
    description?: string;
};

export type FilterFacet = {
    key: string;
    label: string;
    options: FilterFacetOption[];
    multiple?: boolean;
    layout?: 'chips' | 'list';
    defaultValue?: FilterValue | FilterValue[];
};

export type DateFilter = {
    startKey?: string;
    endKey?: string;
    startValue?: string | null;
    endValue?: string | null;
    startLabel?: string;
    endLabel?: string;
    validateSameYear?: boolean;
};

type FiltrosBaseProps = {
    route: GetRoute;
    defaultSearch?: string;
    placeholder?: string;
    query?: Record<string, QueryValue | null | undefined>;
    facets?: FilterFacet[];
    showSearch?: boolean;
    showDates?: boolean;
    dates?: DateFilter;
    loading?: boolean;
    title?: string;
    description?: string;
    children?: ReactNode;
};

const presets = [
    { key: 'today', label: 'Hoy' },
    { key: 'yesterday', label: 'Ayer' },
    { key: 'last30', label: 'Últimos 30 días' },
    { key: 'month', label: 'Este mes' },
] as const;

function valueKey(value: FilterValue): string {
    return String(value);
}

function valuesFrom(
    facet: FilterFacet,
    value: QueryValue | null | undefined,
): string[] {
    const source = value ?? facet.defaultValue;

    if (source === null || source === undefined || source === '') {
        return [];
    }

    const values = (Array.isArray(source) ? source : [source]).map(valueKey);

    return facet.multiple ? values : values.slice(0, 1);
}

function defaultSelections(facets: FilterFacet[]): FilterSelections {
    return Object.fromEntries(
        facets.map((facet) => [facet.key, valuesFrom(facet, undefined)]),
    );
}

function currentSelections(
    facets: FilterFacet[],
    query: FiltrosBaseProps['query'],
): FilterSelections {
    return Object.fromEntries(
        facets.map((facet) => [
            facet.key,
            valuesFrom(facet, query?.[facet.key]),
        ]),
    );
}

function sameSelection(first: string[], second: string[]): boolean {
    return (
        first.length === second.length &&
        first.every((value) => second.includes(value))
    );
}

function localDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function presetDates(key: (typeof presets)[number]['key']): [string, string] {
    const today = new Date();
    const start = new Date(today);
    const end = new Date(today);

    if (key === 'yesterday') {
        start.setDate(start.getDate() - 1);
        end.setDate(end.getDate() - 1);
    }

    if (key === 'last30') {
        start.setDate(start.getDate() - 29);
    }

    if (key === 'month') {
        start.setDate(1);
    }

    return [localDate(start), localDate(end)];
}

function facetSignature(
    facets: FilterFacet[],
    query: FiltrosBaseProps['query'],
): string {
    return JSON.stringify(
        facets.map((facet) => ({
            key: facet.key,
            value: query?.[facet.key] ?? facet.defaultValue ?? null,
            options: facet.options.map((option) => option.value),
        })),
    );
}

export function FiltrosBase({
    route,
    defaultSearch = '',
    placeholder = 'Buscar…',
    query = {},
    facets = [],
    showSearch = true,
    showDates = false,
    dates = {},
    loading = false,
    title = 'Filtros',
    description = 'Combina criterios para encontrar registros con precisión.',
    children,
}: FiltrosBaseProps) {
    const isMobile = useIsMobile();
    const startKey = dates.startKey ?? 'fecha_inicio';
    const endKey = dates.endKey ?? 'fecha_fin';
    const initialStart = dates.startValue ?? String(query[startKey] ?? '');
    const initialEnd = dates.endValue ?? String(query[endKey] ?? '');
    const selectionSignature = facetSignature(facets, query);
    const dateSignature = `${initialStart}|${initialEnd}`;
    const initialSelections = currentSelections(facets, query);
    const [searchState, setSearchState] = useState({
        source: defaultSearch,
        value: defaultSearch,
    });
    const [selectionState, setSelectionState] = useState({
        source: selectionSignature,
        value: initialSelections,
    });
    const [dateState, setDateState] = useState({
        source: dateSignature,
        start: initialStart,
        end: initialEnd,
    });
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [dateError, setDateError] = useState<string | null>(null);
    const search =
        searchState.source === defaultSearch
            ? searchState.value
            : defaultSearch;
    const selections =
        selectionState.source === selectionSignature
            ? selectionState.value
            : initialSelections;
    const startDate =
        dateState.source === dateSignature ? dateState.start : initialStart;
    const endDate =
        dateState.source === dateSignature ? dateState.end : initialEnd;

    const setSearch = (value: string) => {
        setSearchState({ source: defaultSearch, value });
    };

    const setSelections = (
        next:
            | FilterSelections
            | ((current: FilterSelections) => FilterSelections),
    ) => {
        setSelectionState((current) => {
            const currentValue =
                current.source === selectionSignature
                    ? current.value
                    : initialSelections;

            return {
                source: selectionSignature,
                value: typeof next === 'function' ? next(currentValue) : next,
            };
        });
    };

    const setStartDate = (value: string) => {
        setDateState((current) => {
            const currentEnd =
                current.source === dateSignature ? current.end : initialEnd;

            return {
                source: dateSignature,
                start: value,
                end: currentEnd,
            };
        });
    };

    const setEndDate = (value: string) => {
        setDateState((current) => {
            const currentStart =
                current.source === dateSignature ? current.start : initialStart;

            return {
                source: dateSignature,
                start: currentStart,
                end: value,
            };
        });
    };

    const defaults = useMemo(() => defaultSelections(facets), [facets]);
    const activeFacetCount = facets.reduce((count, facet) => {
        const selected = selections[facet.key] ?? [];
        const baseline = valuesFrom(facet, undefined);

        return (
            count + (sameSelection(selected, baseline) ? 0 : selected.length)
        );
    }, 0);
    const activeDateCount = showDates && (startDate || endDate) ? 1 : 0;
    const activeFilterCount = activeFacetCount + activeDateCount;
    const hasFilterPanel = showDates || facets.length > 0 || children;

    const buildQuery = (
        nextSearch: string,
        nextSelections: FilterSelections,
        nextStartDate: string,
        nextEndDate: string,
    ): Record<string, QueryValue> => {
        const payload: Record<string, QueryValue> = {};
        const managedKeys = new Set([
            'search',
            'page',
            ...facets.map((facet) => facet.key),
            startKey,
            endKey,
        ]);

        Object.entries(query).forEach(([key, value]) => {
            if (
                !managedKeys.has(key) &&
                value !== null &&
                value !== undefined
            ) {
                payload[key] = value;
            }
        });

        if (nextSearch.trim()) {
            payload.search = nextSearch.trim();
        }

        facets.forEach((facet) => {
            const selected = nextSelections[facet.key] ?? [];
            const baseline = valuesFrom(facet, undefined);

            if (selected.length === 0 || sameSelection(selected, baseline)) {
                return;
            }

            const values = selected.map((selectedValue) => {
                return (
                    facet.options.find(
                        (option) => valueKey(option.value) === selectedValue,
                    )?.value ?? selectedValue
                );
            });

            payload[facet.key] = facet.multiple ? values : values[0];
        });

        if (showDates && nextStartDate) {
            payload[startKey] = nextStartDate;
        }

        if (showDates && nextEndDate) {
            payload[endKey] = nextEndDate;
        }

        return payload;
    };

    const visit = (
        nextSearch = search,
        nextSelections = selections,
        nextStartDate = startDate,
        nextEndDate = endDate,
    ) => {
        setProcessing(true);
        router.get(
            route.url,
            buildQuery(nextSearch, nextSelections, nextStartDate, nextEndDate),
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        visit();
    };

    const toggleFacet = (facet: FilterFacet, value: FilterValue) => {
        const key = valueKey(value);

        setSelections((current) => {
            const selected = current[facet.key] ?? [];
            const next = facet.multiple
                ? selected.includes(key)
                    ? selected.filter((item) => item !== key)
                    : [...selected, key]
                : [key];

            return { ...current, [facet.key]: next };
        });
    };

    const clearSearch = () => {
        setSearch('');
        visit('', selections, startDate, endDate);
    };

    const resetDraft = () => {
        setSelections(defaults);
        setStartDate('');
        setEndDate('');
        setDateError(null);
    };

    const clearAll = () => {
        setSearch('');
        resetDraft();
        setOpen(false);
        visit('', defaults, '', '');
    };

    const applyFilters = () => {
        if (startDate && endDate && startDate > endDate) {
            setDateError('Fecha inicial debe ser anterior a fecha final.');

            return;
        }

        if (
            dates.validateSameYear &&
            startDate &&
            endDate &&
            startDate.slice(0, 4) !== endDate.slice(0, 4)
        ) {
            setDateError('Rango de fechas debe pertenecer al mismo año.');

            return;
        }

        setDateError(null);
        setOpen(false);
        visit();
    };

    const applyPreset = (key: (typeof presets)[number]['key']) => {
        const [start, end] = presetDates(key);
        setStartDate(start);
        setEndDate(end);
        setDateError(null);
    };

    return (
        <section
            aria-label="Búsqueda y filtros"
            className="relative overflow-hidden rounded-2xl border bg-card/90 p-3 shadow-sm backdrop-blur-sm sm:p-4"
        >
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-10 -top-16 h-24 rounded-full bg-primary/10 blur-3xl"
            />
            <form
                role="search"
                onSubmit={submitSearch}
                className="relative flex flex-col gap-2 sm:flex-row"
            >
                {showSearch && (
                    <div className="group relative min-w-0 flex-1">
                        <Search
                            aria-hidden="true"
                            className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground transition-colors group-focus-within:text-primary"
                        />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={placeholder}
                            aria-label={placeholder}
                            className="h-11 rounded-xl bg-background/80 pr-10 pl-10 shadow-none"
                        />
                        {search && (
                            <button
                                type="button"
                                onClick={clearSearch}
                                disabled={processing || loading}
                                aria-label="Limpiar búsqueda"
                                className="absolute top-1/2 right-2.5 grid size-7 -translate-y-1/2 place-items-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                            >
                                <X className="size-4" />
                            </button>
                        )}
                    </div>
                )}

                <div className="flex gap-2">
                    {showSearch && (
                        <Button
                            type="submit"
                            disabled={processing || loading}
                            className="h-11 flex-1 rounded-xl px-5 sm:flex-none"
                        >
                            <Search />
                            Buscar
                        </Button>
                    )}

                    {hasFilterPanel && (
                        <Sheet open={open} onOpenChange={setOpen}>
                            <SheetTrigger asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className={cn(
                                        'relative h-11 flex-1 rounded-xl bg-background/80 px-4 sm:flex-none',
                                        activeFilterCount > 0 &&
                                            'border-primary/50 bg-primary/5 text-primary',
                                    )}
                                >
                                    <SlidersHorizontal />
                                    Filtros
                                    {activeFilterCount > 0 && (
                                        <Badge className="min-w-5 rounded-full px-1.5 tabular-nums">
                                            {activeFilterCount}
                                        </Badge>
                                    )}
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side={isMobile ? 'bottom' : 'right'}
                                className={cn(
                                    'gap-0 overflow-hidden p-0',
                                    isMobile
                                        ? 'max-h-[90svh] rounded-t-3xl'
                                        : 'w-full sm:max-w-md',
                                )}
                            >
                                <SheetHeader className="border-b bg-muted/35 px-5 py-5 text-left">
                                    <SheetTitle className="text-lg">
                                        {title}
                                    </SheetTitle>
                                    <SheetDescription>
                                        {description}
                                    </SheetDescription>
                                </SheetHeader>

                                <div className="flex-1 space-y-7 overflow-y-auto px-5 py-6">
                                    {showDates && (
                                        <fieldset className="space-y-4">
                                            <legend className="flex items-center gap-2 text-sm font-semibold">
                                                <CalendarDays className="size-4 text-primary" />
                                                Periodo
                                            </legend>
                                            <div className="flex flex-wrap gap-2">
                                                {presets.map((preset) => (
                                                    <Button
                                                        key={preset.key}
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="rounded-full"
                                                        onClick={() =>
                                                            applyPreset(
                                                                preset.key,
                                                            )
                                                        }
                                                    >
                                                        {preset.label}
                                                    </Button>
                                                ))}
                                            </div>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label
                                                        htmlFor={`${startKey}-filter`}
                                                    >
                                                        {dates.startLabel ??
                                                            'Fecha inicial'}
                                                    </Label>
                                                    <Input
                                                        id={`${startKey}-filter`}
                                                        type="date"
                                                        value={startDate}
                                                        max={
                                                            endDate || undefined
                                                        }
                                                        onChange={(event) => {
                                                            setStartDate(
                                                                event.target
                                                                    .value,
                                                            );
                                                            setDateError(null);
                                                        }}
                                                        className="scheme-light-dark"
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label
                                                        htmlFor={`${endKey}-filter`}
                                                    >
                                                        {dates.endLabel ??
                                                            'Fecha final'}
                                                    </Label>
                                                    <Input
                                                        id={`${endKey}-filter`}
                                                        type="date"
                                                        value={endDate}
                                                        min={
                                                            startDate ||
                                                            undefined
                                                        }
                                                        onChange={(event) => {
                                                            setEndDate(
                                                                event.target
                                                                    .value,
                                                            );
                                                            setDateError(null);
                                                        }}
                                                        className="scheme-light-dark"
                                                    />
                                                </div>
                                            </div>
                                            {dateError && (
                                                <p
                                                    role="alert"
                                                    className="text-sm text-destructive"
                                                >
                                                    {dateError}
                                                </p>
                                            )}
                                        </fieldset>
                                    )}

                                    {facets.map((facet) => (
                                        <fieldset
                                            key={facet.key}
                                            className="space-y-3"
                                        >
                                            <legend className="text-sm font-semibold">
                                                {facet.label}
                                            </legend>
                                            <div
                                                className={cn(
                                                    'flex flex-wrap gap-2',
                                                    facet.layout === 'list' &&
                                                        'flex-col',
                                                )}
                                            >
                                                {facet.options.map((option) => {
                                                    const selected = (
                                                        selections[facet.key] ??
                                                        []
                                                    ).includes(
                                                        valueKey(option.value),
                                                    );

                                                    return (
                                                        <button
                                                            key={valueKey(
                                                                option.value,
                                                            )}
                                                            type="button"
                                                            aria-pressed={
                                                                selected
                                                            }
                                                            onClick={() =>
                                                                toggleFacet(
                                                                    facet,
                                                                    option.value,
                                                                )
                                                            }
                                                            className={cn(
                                                                'group flex min-h-10 items-center gap-2.5 rounded-xl border px-3 py-2 text-left text-sm font-medium transition-all focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                                                                facet.layout ===
                                                                    'list' &&
                                                                    'w-full',
                                                                selected
                                                                    ? 'border-primary/50 bg-primary/10 text-primary shadow-xs'
                                                                    : 'bg-background hover:border-primary/35 hover:bg-primary/5',
                                                            )}
                                                        >
                                                            <span
                                                                className={cn(
                                                                    'grid size-5 shrink-0 place-items-center rounded-md border transition-colors',
                                                                    selected
                                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                                        : 'border-input bg-background group-hover:border-primary/50',
                                                                )}
                                                            >
                                                                {selected && (
                                                                    <Check className="size-3.5" />
                                                                )}
                                                            </span>
                                                            <span>
                                                                <span className="block">
                                                                    {
                                                                        option.label
                                                                    }
                                                                </span>
                                                                {option.description && (
                                                                    <span className="mt-0.5 block text-xs font-normal text-muted-foreground">
                                                                        {
                                                                            option.description
                                                                        }
                                                                    </span>
                                                                )}
                                                            </span>
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </fieldset>
                                    ))}

                                    {children}
                                </div>

                                <SheetFooter className="grid grid-cols-2 border-t bg-muted/35 px-5 py-4 sm:grid-cols-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={resetDraft}
                                        disabled={processing || loading}
                                        className="h-11 rounded-xl"
                                    >
                                        Limpiar
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={applyFilters}
                                        disabled={processing || loading}
                                        className="h-11 rounded-xl"
                                    >
                                        Aplicar filtros
                                    </Button>
                                </SheetFooter>
                            </SheetContent>
                        </Sheet>
                    )}
                </div>
            </form>

            {(activeFilterCount > 0 || defaultSearch) && (
                <div className="relative mt-3 flex items-center justify-between gap-3 border-t pt-3">
                    <p className="text-xs text-muted-foreground">
                        {activeFilterCount > 0
                            ? `${activeFilterCount} ${activeFilterCount === 1 ? 'filtro activo' : 'filtros activos'}`
                            : 'Búsqueda activa'}
                    </p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={clearAll}
                        disabled={processing || loading}
                        className="h-7 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <X />
                        Limpiar todo
                    </Button>
                </div>
            )}
        </section>
    );
}
