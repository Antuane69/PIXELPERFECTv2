import type { ReactNode } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

type ResourceDetailDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: ReactNode;
    description?: ReactNode;
    headerExtra?: ReactNode;
    footer?: ReactNode;
    children: ReactNode;
    className?: string;
    bodyClassName?: string;
};

export function ResourceDetailDrawer({
    open,
    onOpenChange,
    title,
    description,
    headerExtra,
    footer,
    children,
    className,
    bodyClassName,
}: ResourceDetailDrawerProps) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                className={cn(
                    'w-full gap-0 overflow-hidden p-0 sm:max-w-xl lg:max-w-2xl',
                    className,
                )}
            >
                <SheetHeader className="shrink-0 gap-3 border-b bg-muted/35 px-5 py-5 pr-12 text-left sm:px-6">
                    <div className="grid gap-1.5">
                        <SheetTitle className="text-xl leading-tight">
                            {title}
                        </SheetTitle>
                        {description ? (
                            <SheetDescription>{description}</SheetDescription>
                        ) : (
                            <SheetDescription className="sr-only">
                                Detalle del registro
                            </SheetDescription>
                        )}
                    </div>
                    {headerExtra ? (
                        <div className="flex flex-wrap items-center gap-2">
                            {headerExtra}
                        </div>
                    ) : null}
                </SheetHeader>

                <div
                    className={cn(
                        'min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6',
                        bodyClassName,
                    )}
                >
                    {children}
                </div>

                {footer ? (
                    <SheetFooter className="shrink-0 flex-row flex-wrap justify-end border-t bg-background/95 px-5 py-4 backdrop-blur-sm sm:px-6">
                        {footer}
                    </SheetFooter>
                ) : null}
            </SheetContent>
        </Sheet>
    );
}
