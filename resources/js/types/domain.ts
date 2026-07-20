export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
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
    mime_type?: string | null;
    tamano?: number | null;
    tipo?: Pick<TipoDocumentoEmpleado, 'id' | 'nombre'> | null;
    tipo_documento?: Pick<TipoDocumentoEmpleado, 'id' | 'nombre'> | null;
};

export type Empleado = {
    id: number;
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
    domicilio: string;
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
    fecha_contrato_siguiente?: string | null;
    fecha_contrato_indefinido?: string | null;
    fecha_ultimo_aviso?: string | null;
    fecha_evaluacion?: string | null;
    fecha_inicio_contrato?: string | null;
    fecha_termino_contrato?: string | null;
    documentos?: EmpleadoDocumento[];
    deleted_at?: string | null;
};

export type DashboardStats = {
    users: number | null;
    empleados: number | null;
    puestosActivos: number | null;
    tiposDocumentoActivos: number | null;
};
