import type { ReactNode } from 'react';

type ResourceHeaderProps = {
    title: string;
    description: string;
    actions?: ReactNode;
};

export function ResourceHeader({
    title,
    description,
    actions,
}: ResourceHeaderProps) {
    return (
        <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="grid gap-1">
                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {title}
                </h1>
                <p className="max-w-2xl text-sm text-muted-foreground sm:text-base">
                    {description}
                </p>
            </div>
            {actions ? <div className="shrink-0">{actions}</div> : null}
        </header>
    );
}
