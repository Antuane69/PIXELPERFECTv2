import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type GetRoute = {
    url: string;
};

type ResourceSearchProps = {
    route: GetRoute;
    defaultValue?: string;
    placeholder?: string;
    query?: Record<string, string | number | boolean>;
};

export function ResourceSearch({
    route,
    defaultValue = '',
    placeholder = 'Buscar…',
    query = {},
}: ResourceSearchProps) {
    const [search, setSearch] = useState(defaultValue);
    const [processing, setProcessing] = useState(false);

    const visit = (value: string) => {
        setProcessing(true);
        router.get(
            route.url,
            { ...query, ...(value ? { search: value } : {}) },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        visit(search.trim());
    };

    const clear = () => {
        setSearch('');
        visit('');
    };

    return (
        <form
            role="search"
            onSubmit={submit}
            className="flex w-full flex-col gap-2 sm:flex-row"
        >
            <div className="relative min-w-0 flex-1 sm:max-w-md">
                <Search
                    aria-hidden="true"
                    className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder={placeholder}
                    className="pl-9"
                    aria-label={placeholder}
                />
            </div>
            <div className="flex gap-2">
                <Button type="submit" disabled={processing}>
                    <Search />
                    Buscar
                </Button>
                {(defaultValue || search) && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={clear}
                        disabled={processing}
                    >
                        <X />
                        Limpiar
                    </Button>
                )}
            </div>
        </form>
    );
}
