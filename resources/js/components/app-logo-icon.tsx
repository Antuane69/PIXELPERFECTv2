import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon({
    className,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/brand/pixel-perfect-mark.png"
            alt=""
            aria-hidden="true"
            className={className}
            {...props}
        />
    );
}
