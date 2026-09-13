import { router, usePage } from '@inertiajs/react';
import {
    Building2,
    Check,
    ChevronsUpDown,
    Landmark,
    LockKeyhole,
} from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import {
    destroy as clearEmpresaContext,
    store as storeEmpresaContext,
} from '@/routes/empresa-contexto';

export function EmpresaSwitcher() {
    const { auth, empresas } = usePage().props;

    if (!empresas.disponibles.length) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="group-data-[collapsible=icon]:justify-center! group-data-[collapsible=icon]:gap-0! group-data-[collapsible=icon]:p-0!"
                            tooltip={{
                                children:
                                    empresas.activa?.nombre ??
                                    'Seleccionar empresa',
                            }}
                        >
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Building2 className="size-4" />
                            </span>
                            <span className="grid min-w-0 flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden">
                                <span className="truncate font-medium">
                                    {empresas.activa?.nombre ??
                                        'Seleccionar empresa'}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {empresas.activa?.grupo?.nombre ??
                                        'Contexto empresarial'}
                                </span>
                            </span>
                            <ChevronsUpDown className="ml-auto size-4 group-data-[collapsible=icon]:hidden" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-64"
                        align="start"
                    >
                        <DropdownMenuLabel>
                            Empresas disponibles
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {auth.user?.es_superadministrador_plataforma && (
                            <>
                                <DropdownMenuItem
                                    className="gap-2"
                                    disabled={!empresas.activa}
                                    onSelect={() =>
                                        router.delete(clearEmpresaContext.url())
                                    }
                                >
                                    <Landmark className="size-4" />
                                    <span className="min-w-0 flex-1 truncate">
                                        Administración de plataforma
                                    </span>
                                    {!empresas.activa ? (
                                        <Check className="size-4" />
                                    ) : null}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                            </>
                        )}
                        {empresas.disponibles.map((empresa) => {
                            const active = empresas.activa?.id === empresa.id;

                            if (!empresa.puede_acceder) {
                                return (
                                    <DropdownMenuItem
                                        key={empresa.id}
                                        disabled
                                        className="gap-2"
                                    >
                                        <LockKeyhole className="size-4" />
                                        <span className="min-w-0 flex-1 truncate">
                                            {empresa.nombre}
                                        </span>
                                        <span className="text-xs">
                                            {empresa.estado}
                                        </span>
                                    </DropdownMenuItem>
                                );
                            }

                            return (
                                <DropdownMenuItem
                                    key={empresa.id}
                                    className="gap-2"
                                    disabled={active}
                                    onSelect={() =>
                                        router.post(storeEmpresaContext.url(), {
                                            empresa_id: empresa.id,
                                        })
                                    }
                                >
                                    <Building2 className="size-4" />
                                    <span className="min-w-0 flex-1 truncate">
                                        {empresa.nombre}
                                    </span>
                                    {active ? (
                                        <Check className="size-4" />
                                    ) : null}
                                </DropdownMenuItem>
                            );
                        })}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
