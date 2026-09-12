import { Link, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, LockKeyhole } from 'lucide-react';
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
import { inicio as empresaInicio } from '@/routes/empresas';

export function EmpresaSwitcher() {
    const { empresas } = usePage().props;

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
                            tooltip={{
                                children:
                                    empresas.activa?.nombre ??
                                    'Seleccionar empresa',
                            }}
                        >
                            <span className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Building2 className="size-4" />
                            </span>
                            <span className="grid min-w-0 flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-medium">
                                    {empresas.activa?.nombre ??
                                        'Seleccionar empresa'}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {empresas.activa?.grupo?.nombre ??
                                        'Contexto empresarial'}
                                </span>
                            </span>
                            <ChevronsUpDown className="ml-auto size-4" />
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
                                <DropdownMenuItem key={empresa.id} asChild>
                                    <Link
                                        href={empresaInicio(empresa.slug)}
                                        className="flex w-full items-center gap-2"
                                    >
                                        <Building2 className="size-4" />
                                        <span className="min-w-0 flex-1 truncate">
                                            {empresa.nombre}
                                        </span>
                                        {active ? (
                                            <Check className="size-4" />
                                        ) : null}
                                    </Link>
                                </DropdownMenuItem>
                            );
                        })}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
