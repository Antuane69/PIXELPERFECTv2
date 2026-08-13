import { Image as AntImage } from 'antd';
import { Search } from 'lucide-react';
import { useState } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type ImagePreviewProps = {
    src: string;
    active: boolean;
};

export function ImagePreview({ src, active }: ImagePreviewProps) {
    const [open, setOpen] = useState(false);

    if (!active || !src) {
        return null;
    }

    return (
        <AntImage
            src={src}
            alt="Vista previa de imagen"
            width={48}
            height={48}
            className="size-12 shrink-0 rounded-md object-cover"
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
    const dialogs = document.querySelectorAll<HTMLElement>(
        '[data-slot="dialog-content"][data-state="open"]',
    );

    return dialogs.item(dialogs.length - 1) ?? document.body;
}
