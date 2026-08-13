import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <div className="group/logo flex min-w-0 flex-1 items-center gap-3 group-data-[collapsible=icon]:justify-center">
            <AppLogoIcon className="size-9 shrink-0 object-contain transition-[width,height,transform] duration-300 ease-in-out group-hover/logo:scale-105 group-data-[collapsible=icon]:size-16 motion-reduce:transition-none" />
            <span className="flex min-w-0 items-baseline text-base leading-none tracking-[-0.08em] group-data-[collapsible=icon]:hidden">
                <span className="font-black">PIXEL</span>
                <span className="font-serif font-semibold text-[#a855f7] italic dark:text-[#d8b4fe]">
                    PERFECT
                </span>
            </span>
        </div>
    );
}
