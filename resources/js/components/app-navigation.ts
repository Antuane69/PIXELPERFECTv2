import {
    BriefcaseBusiness,
    FileCheck2,
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
import type { NavItem } from '@/types';

export const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutDashboard,
    },
    {
        title: 'Usuarios',
        href: usersIndex(),
        icon: UserRoundCog,
        permission: 'users.view',
    },
    {
        title: 'Roles',
        href: rolesIndex(),
        icon: ShieldCheck,
        permission: 'roles.view',
    },
    {
        title: 'Empleados',
        href: empleadosIndex(),
        icon: UsersRound,
        permission: 'empleados.view',
    },
    {
        title: 'Puestos',
        href: puestosIndex(),
        icon: BriefcaseBusiness,
        permission: 'puestos.view',
    },
    {
        title: 'Tipos de documento',
        href: tiposDocumentoIndex(),
        icon: FileCheck2,
        permission: 'tipos_documento.view',
    },
];

export function visibleNavItems(items: NavItem[], permissions: string[]) {
    return items.filter(
        (item) =>
            !item.permission ||
            permissions.includes('*') ||
            permissions.includes(item.permission),
    );
}
