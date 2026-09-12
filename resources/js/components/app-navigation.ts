import {
    BriefcaseBusiness,
    Building2,
    FileCheck2,
    FileText,
    BadgeDollarSign,
    LayoutDashboard,
    ShieldCheck,
    UserRoundCog,
    UsersRound,
} from 'lucide-react';
import { index as empleadosIndex } from '@/actions/App/Http/Controllers/EmpleadoController';
import { index as puestosIndex } from '@/actions/App/Http/Controllers/PuestoController';
import { index as rolesIndex } from '@/actions/App/Http/Controllers/RoleController';
import { index as tiposDocumentoIndex } from '@/actions/App/Http/Controllers/TipoDocumentoEmpleadoController';
import { index as usersIndex } from '@/actions/App/Http/Controllers/UserController';
import { dashboard } from '@/routes';
import { inicio as empresaInicio } from '@/routes/empresas';
import { index as logsIndex } from '@/routes/logs';
import { index as empresasPlatformIndex } from '@/routes/platform/empresas';
import { index as planesPlatformIndex } from '@/routes/platform/planes';
import type { NavItem } from '@/types';

export function mainNavItems(empresaSlug?: string | null): NavItem[] {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: empresaSlug ? empresaInicio(empresaSlug) : dashboard(),
            icon: LayoutDashboard,
        },
        {
            title: 'Logs del sistema',
            href: logsIndex(),
            icon: FileText,
            platformOnly: true,
        },
        {
            title: 'Empresas',
            href: empresasPlatformIndex(),
            icon: Building2,
            platformOnly: true,
        },
        {
            title: 'Planes',
            href: planesPlatformIndex(),
            icon: BadgeDollarSign,
            platformOnly: true,
        },
        {
            title: 'Tipos de documento',
            href: tiposDocumentoIndex(),
            icon: FileCheck2,
            platformOnly: true,
        },
    ];

    if (empresaSlug) {
        items.push(
            {
                title: 'Usuarios',
                href: usersIndex(empresaSlug),
                icon: UserRoundCog,
                permission: 'users.view',
                module: 'usuarios',
            },
            {
                title: 'Roles',
                href: rolesIndex(empresaSlug),
                icon: ShieldCheck,
                permission: 'roles.view',
                module: 'roles',
            },
            {
                title: 'Puestos',
                href: puestosIndex(empresaSlug),
                icon: BriefcaseBusiness,
                permission: 'puestos.view',
                module: 'puestos',
            },
            {
                title: 'Empleados',
                href: empleadosIndex(empresaSlug),
                icon: UsersRound,
                permission: 'empleados.view',
                module: 'empleados',
            },
        );
    }

    return items;
}

export function visibleNavItems(
    items: NavItem[],
    permissions: string[],
    isPlatformAdministrator = false,
    enabledModules: string[] = [],
) {
    return items.filter((item) => {
        if (item.platformOnly && !isPlatformAdministrator) {
            return false;
        }

        if (item.module && !enabledModules.includes(item.module)) {
            return false;
        }

        return (
            !item.permission ||
            permissions.includes('*') ||
            permissions.includes(item.permission)
        );
    });
}
