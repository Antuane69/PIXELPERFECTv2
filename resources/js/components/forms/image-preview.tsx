import { Image as AntImage } from 'antd';
import { useState } from 'react';

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
                cover: 'Ver imagen',
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
