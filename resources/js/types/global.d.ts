import type { Auth, EmpresasCompartidas } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            empresas: EmpresasCompartidas;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
