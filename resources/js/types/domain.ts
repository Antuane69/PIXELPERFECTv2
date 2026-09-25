export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
    page?: number | null;
};

export type LaravelPaginator<T> = {
    data: T[];
    current_page: number;
    first_page_url?: string;
    from: number | null;
    last_page: number;
    last_page_url?: string;
    links: PaginationLink[];
    next_page_url: string | null;
    path?: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

export type Permission = {
    id: number;
    name: string;
};

export type Role = {
    id: number;
    name: string;
    permissions?: Array<Permission | string>;
    users_count?: number;
};

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    two_factor_enabled: boolean;
    roles?: Array<Role | string>;
    created_at?: string | null;
};

export type Puesto = {
    id: number;
    nombre: string;
    salario_dia: number | string | null;
    salario_quincena: number | string | null;
    activo: boolean;
    deleted_at?: string | null;
};

export type EmpleadoCarpeta = {
    id: number;
    nombre: string;
    activo: boolean;
    creado_por_id: number;
    creado_por: Pick<ManagedUser, 'id' | 'name'> | null;
    usuarios_con_acceso: Array<Pick<ManagedUser, 'id' | 'name'>>;
    deleted_at?: string | null;
};

export type EmpleadoDocumentoCatalogo = {
    id: number;
    nombre: string;
    empleado_carpeta_id: number;
    contenido_html?: string;
    modulo_ids?: number[];
    modulos?: Pick<Modulo, 'id' | 'clave' | 'nombre'>[];
    carpeta_nombre: string | null;
    updated_at: string | null;
    deleted_at: string | null;
};

export type EmpleadoDocumentoCatalogoVariable = {
    key: string;
    label: string;
};

export type TipoDocumentoEmpleado = {
    id: number;
    nombre: string;
    documentos_aceptados: string[];
    activo: boolean;
    es_renovable: boolean;
    frecuencia_cantidad: number | null;
    frecuencia_tipo: string | null;
    deleted_at?: string | null;
};

export type EmpleadoDocumento = {
    id: number;
    tipo_documento_empleado_id?: number;
    nombre_original?: string | null;
    nombre?: string | null;
    vence_el?: string | null;
    download_url?: string | null;
    preview_url?: string | null;
    mime_type?: string | null;
    tamano?: number | null;
    tipo?: Pick<TipoDocumentoEmpleado, 'id' | 'nombre'> | null;
    tipo_documento?: Pick<TipoDocumentoEmpleado, 'id' | 'nombre'> | null;
};

export type Empleado = {
    id: number;
    empresa_id?: number;
    nombre: string;
    nombre_usuario: string;
    correo: string;
    curp: string;
    rfc: string;
    nss: string | null;
    num_clinica_ss: string | null;
    puesto_id: number | null;
    puesto?: Puesto | null;
    estado_civil: string;
    sexo: string;
    domicilio: string | null;
    telefono: string;
    avatar_url?: string | null;
    salario_dia?: number | string | null;
    salario_quincena?: number | string | null;
    salario_vacaciones_finiquito?: number | string | null;
    aguinaldo?: number | string | null;
    prima_vacacional?: number | string | null;
    dias_vacaciones?: number | null;
    dias_liquidacion?: number | null;
    dias_descanso?: string[];
    fecha_nacimiento?: string | null;
    fecha_ingreso?: string | null;
    periodo_prueba_meses?: number | null;
    fecha_contrato_siguiente?: string | null;
    fecha_contrato_indefinido?: string | null;
    fecha_ultimo_aviso?: string | null;
    fecha_evaluacion?: string | null;
    fecha_inicio_contrato?: string | null;
    fecha_termino_contrato?: string | null;
    documentos?: EmpleadoDocumento[];
    created_at?: string | null;
    updated_at?: string | null;
    deleted_at?: string | null;
};

export type DashboardStats = {
    empresas: number;
    users: number;
    planes: number;
    modules: number;
    permissions: number;
};

export type GrupoEmpresarial = {
    id: number;
    nombre: string;
};

export type Modulo = {
    id: number;
    clave: string;
    nombre: string;
    descripcion?: string | null;
};

export type PlatformModule = Modulo & {
    activo: boolean;
    orden: number;
    empresas_count: number;
    permisos_count: number;
    created_at: string | null;
};

export type PermissionScope = 'EMPRESA' | 'PLATAFORMA';

export type PlatformPermission = {
    id: number;
    name: string;
    alcance: PermissionScope;
    modulo_id: number | null;
    modulo: Pick<Modulo, 'id' | 'clave' | 'nombre'> | null;
    roles_count: number;
    created_at: string | null;
};

export type ModuloEmpresa = Modulo & {
    habilitado: boolean;
};

export type PlanIconName =
    | 'ApartmentOutlined'
    | 'AppstoreOutlined'
    | 'BankOutlined'
    | 'BulbOutlined'
    | 'CloudOutlined'
    | 'CrownOutlined'
    | 'DatabaseOutlined'
    | 'ExperimentOutlined'
    | 'FireOutlined'
    | 'GlobalOutlined'
    | 'HeartOutlined'
    | 'RocketOutlined'
    | 'SafetyCertificateOutlined'
    | 'ShopOutlined'
    | 'SmileOutlined'
    | 'StarOutlined'
    | 'TeamOutlined'
    | 'ThunderboltOutlined'
    | 'ToolOutlined'
    | 'TrophyOutlined';

export type Plan = {
    id: number;
    nombre: string;
    precio_mensual: number | string;
    color: string;
    icono: PlanIconName;
    modulos_incluidos: string | null;
    limite_usuarios: number | null;
    periodo_gracia_dias: number;
    activo: boolean;
    deleted_at: string | null;
};

export type EmpresaAdministrada = {
    id: number;
    nombre_legal: string;
    nombre_comercial: string | null;
    slug: string;
    logo_url: string | null;
    estado: 'PROSPECTO' | 'DEMO' | 'ACTIVA' | 'VENCIDA' | 'DESACTIVADA';
    demo_ends_at: string | null;
    membresias_count: number;
    grupo_empresarial: GrupoEmpresarial;
    modulos: ModuloEmpresa[];
    created_at: string;
};
