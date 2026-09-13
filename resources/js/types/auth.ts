export type User = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    roles?: string[];
    permissions?: string[];
    es_superadministrador_plataforma: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
};

export type EmpresaResumen = {
    id: number;
    nombre: string;
    nombre_legal: string;
    estado: string;
    modulos?: string[];
    puede_acceder?: boolean;
    grupo?: {
        id: number;
        nombre: string;
    } | null;
};

export type EmpresasCompartidas = {
    activa: EmpresaResumen | null;
    disponibles: EmpresaResumen[];
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
