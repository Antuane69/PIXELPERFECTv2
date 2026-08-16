import { Image as AntImage } from 'antd';
import { Search } from 'lucide-react';
import { useState } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type ImagePreviewProps = {
    src: string;
    active: boolean;
    alt?: string;
    className?: string;
    size?: number;
};

export function ImagePreview({
    src,
    active,
    alt = 'Vista previa de imagen',
    className,
    size = 48,
}: ImagePreviewProps) {
    const [open, setOpen] = useState(false);

    if (!active || !src) {
        return null;
    }

    return (
        <AntImage
            src={src}
            alt={alt}
            width={size}
            height={size}
            className={cn('shrink-0 rounded-md object-cover', className)}
            preview={{
                cover: (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span
                                className="flex size-full items-center justify-center"
                                aria-label="Ver imagen"
                            >
                                <Search className="size-5" aria-hidden="true" />
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>Ver imagen</TooltipContent>
                    </Tooltip>
                ),
                mask: { closable: true },
                open,
                onOpenChange: setOpen,
                zIndex: 100,
                rootClassName: 'pointer-events-auto',
                getContainer: getPreviewContainer,
            }}
        />
    );
}

function getPreviewContainer(): HTMLElement {
    const overlays = document.querySelectorAll<HTMLElement>(
        '[data-slot="dialog-content"][data-state="open"], [data-slot="sheet-content"][data-state="open"]',
    );

    return overlays.item(overlays.length - 1) ?? document.body;
}
