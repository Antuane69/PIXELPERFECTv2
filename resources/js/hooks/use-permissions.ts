import { usePage } from '@inertiajs/react';

export function usePermissions() {
    const permissions = usePage().props.auth.user?.permissions ?? [];

    const can = (permission: string) =>
        permissions.includes('*') || permissions.includes(permission);

    return { can, permissions };
}
