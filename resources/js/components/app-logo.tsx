import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center overflow-hidden rounded-lg bg-primary/10 ring-1 ring-primary/15">
                <AppLogoIcon className="size-8 object-contain" />
            </div>
            <div className="ml-1 grid min-w-0 flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold">
                    Pixel Perfect
                </span>
                <span className="truncate text-xs leading-tight text-muted-foreground">
                    Administración
                </span>
            </div>
        </>
    );
}
