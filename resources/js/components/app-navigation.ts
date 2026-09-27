import {
    BadgeDollarSign,
    BriefcaseBusiness,
    Building2,
    Boxes,
    CalendarDays,
    FileText,
    Folder,
    FolderOpen,
    HeartPulse,
    KeyRound,
    LayoutDashboard,
    ListChecks,
    ShieldCheck,
    ShieldAlert,
    UserRoundCog,
    UsersRound,
} from 'lucide-react';
import { index as carpetasEmpleadosIndex } from '@/actions/App/Http/Controllers/EmpleadoCarpetaController';
import { index as empleadosIndex } from '@/actions/App/Http/Controllers/EmpleadoController';
import { index as documentosCatalogoIndex } from '@/actions/App/Http/Controllers/EmpleadoDocumentoCatalogoController';
import { index as catalogoFaltasReglamentoIndex } from '@/actions/App/Http/Controllers/FaltasReglamento/FaltaReglamentoCatalogoController';
import { index as faltasReglamentoIndex } from '@/actions/App/Http/Controllers/FaltasReglamento/FaltaReglamentoController';
import { index as tiposFaltaReglamentoIndex } from '@/actions/App/Http/Controllers/FaltasReglamento/TipoFaltaReglamentoController';
import { index as incapacidadesIndex } from '@/actions/App/Http/Controllers/Incapacidades/IncapacidadController';
import { index as permisosLaboralesIndex } from '@/actions/App/Http/Controllers/PermisosLaborales/PermisoLaboralController';
import { index as tiposPermisosIndex } from '@/actions/App/Http/Controllers/PermisosLaborales/TipoPermisoController';
import { index as puestosIndex } from '@/actions/App/Http/Controllers/PuestoController';
import { index as rolesIndex } from '@/actions/App/Http/Controllers/RoleController';
import { index as tiposDocumentoIndex } from '@/actions/App/Http/Controllers/TipoDocumentoEmpleadoController';
import { index as usersIndex } from '@/actions/App/Http/Controllers/UserController';
import { index as vacacionesIndex } from '@/actions/App/Http/Controllers/VacacionController';
import { dashboard } from '@/routes';
import { inicio as empresaInicio } from '@/routes/empresas';
import { index as logsIndex } from '@/routes/logs';
import { index as empresasPlatformIndex } from '@/routes/platform/empresas';
import { index as modulosPlatformIndex } from '@/routes/platform/modulos';
import { index as permisosPlatformIndex } from '@/routes/platform/permisos';
import { index as planesPlatformIndex } from '@/routes/platform/planes';
import { index as usuariosPlatformIndex } from '@/routes/platform/usuarios';
import type { NavItem } from '@/types';

export function mainNavItems(hasEmpresaContext = false): NavItem[] {
    if (hasEmpresaContext) {
        return [
            {
                title: 'Dashboard',
                href: empresaInicio(),
                icon: LayoutDashboard,
            },
            {
                title: 'Usuarios',
                href: usersIndex(),
                icon: UserRoundCog,
                permission: 'users.view',
                module: 'usuarios',
            },
            {
                title: 'Roles',
                href: rolesIndex(),
                icon: ShieldCheck,
                permission: 'roles.view',
                module: 'roles',
            },
            {
                title: 'Puestos',
                href: puestosIndex(),
                icon: BriefcaseBusiness,
                permission: 'puestos.view',
                module: 'puestos',
            },
            {
                title: 'Vacaciones',
                href: vacacionesIndex(),
                icon: CalendarDays,
                permissionsAnyOf: ['vacaciones.view', 'vacaciones.review'],
                module: 'vacaciones',
            },
            {
                title: 'Permisos laborales',
                href: permisosLaboralesIndex(),
                icon: CalendarDays,
                permissionsAnyOf: [
                    'permisos_laborales.view',
                    'permisos_laborales.review',
                ],
                module: 'permisos_laborales',
            },
            {
                title: 'Faltas al reglamento',
                href: faltasReglamentoIndex(),
                icon: ShieldAlert,
                permissionsAnyOf: [
                    'faltas_reglamento.view',
                    'faltas_reglamento.create',
                    'faltas_reglamento.review',
                ],
                module: 'faltas_reglamento',
            },
            {
                title: 'Tipos de falta',
                href: tiposFaltaReglamentoIndex(),
                icon: ListChecks,
                permission: 'tipos_falta_reglamento.view',
                module: 'faltas_reglamento',
            },
            {
                title: 'Faltas del catálogo',
                href: catalogoFaltasReglamentoIndex(),
                icon: FileText,
                permission: 'faltas_reglamento_catalogo.view',
                module: 'faltas_reglamento',
            },
            {
                title: 'Incapacidades',
                href: incapacidadesIndex(),
                icon: HeartPulse,
                permissionsAnyOf: [
                    'incapacidades.view',
                    'incapacidades.review',
                ],
                module: 'incapacidades',
            },
            {
                title: 'Tipos de permisos',
                href: tiposPermisosIndex(),
                icon: FileText,
                permission: 'tipos_permisos.view',
                module: 'permisos_laborales',
            },
            {
                title: 'Empleados',
                href: empleadosIndex(),
                icon: UsersRound,
                module: 'empleados',
                children: [
                    {
                        title: 'Panel',
                        href: empleadosIndex(),
                        icon: LayoutDashboard,
                        permission: 'empleados.view',
                        module: 'empleados',
                    },
                    {
                        title: 'Catálogos',
                        href: carpetasEmpleadosIndex(),
                        icon: FolderOpen,
                        children: [
                            {
                                title: 'Carpetas',
                                href: carpetasEmpleadosIndex(),
                                icon: Folder,
                                permission: 'empleados_carpetas.view',
                                module: 'empleados',
                            },
                            {
                                title: 'Documentos',
                                href: documentosCatalogoIndex(),
                                icon: FileText,
                                permission:
                                    'empleados_documentos_catalogo.view',
                                module: 'empleados',
                            },
                            {
                                title: 'Tipos de documento',
                                href: tiposDocumentoIndex(),
                                icon: FileText,
                                permission: 'tipos_documento.view',
                                module: 'empleados',
                            },
                        ],
                    },
                ],
            },
        ];
    }

    return [
        {
            title: 'Dashboard',
            href: dashboard(),
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
            title: 'Usuarios',
            href: usuariosPlatformIndex(),
            icon: UserRoundCog,
            platformOnly: true,
        },
        {
            title: 'Planes',
            href: planesPlatformIndex(),
            icon: BadgeDollarSign,
            platformOnly: true,
        },
        {
            title: 'Módulos',
            href: modulosPlatformIndex(),
            icon: Boxes,
            platformOnly: true,
        },
        {
            title: 'Permisos',
            href: permisosPlatformIndex(),
            icon: KeyRound,
            platformOnly: true,
        },
    ];
}

export function visibleNavItems(
    items: NavItem[],
    permissions: string[],
    isPlatformAdministrator = false,
    enabledModules: string[] = [],
): NavItem[] {
    return items.flatMap((item) => {
        const children = item.children
            ? visibleNavItems(
                  item.children,
                  permissions,
                  isPlatformAdministrator,
                  enabledModules,
              )
            : undefined;

        if (
            (item.platformOnly && !isPlatformAdministrator) ||
            (item.module && !enabledModules.includes(item.module)) ||
            (item.permission &&
                !permissions.includes('*') &&
                !permissions.includes(item.permission)) ||
            (item.permissionsAnyOf &&
                !permissions.includes('*') &&
                !item.permissionsAnyOf.some((permission) =>
                    permissions.includes(permission),
                )) ||
            (item.children && !children?.length)
        ) {
            return [];
        }

        return [{ ...item, ...(children ? { children } : {}) }];
    });
}
