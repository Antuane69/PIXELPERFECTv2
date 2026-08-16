import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import { Toaster as Sonner, toast } from 'sonner';
import type { ExternalToast } from 'sonner';
import { useAppearance } from '@/hooks/use-appearance';
import type { FlashToast } from '@/types/ui';
import './app-alerts.css';

export type AppAlertType = 'success' | 'info' | 'warning' | 'error';

export type AppAlertOptions = Omit<
    ExternalToast,
    'description' | 'title' | 'type'
> & {
    description?: ReactNode;
    message?: ReactNode;
    title?: ReactNode;
    type?: AppAlertType;
};

type BackendErrorAlertOptions = Omit<
    AppAlertOptions,
    'description' | 'type'
> & {
    fallback?: string;
};

export const DEFAULT_BACKEND_ERROR_MESSAGE =
    'Ocurrió un error. Revisa los logs para obtener más información.';

const ERROR_MESSAGE_KEYS = [
    'message',
    'messages',
    'error',
    'errors',
    'data',
    'response',
    'payload',
    'body',
    'result',
    'detail',
    'description',
    'reason',
    'title',
];

const IGNORED_ERROR_KEYS = new Set([
    'code',
    'exception',
    'file',
    'headers',
    'line',
    'stack',
    'status',
    'statusCode',
    'statusText',
    'trace',
]);

const toasterStyle: CSSProperties = {
    '--normal-bg': 'var(--popover)',
    '--normal-text': 'var(--popover-foreground)',
    '--normal-border': 'var(--border)',
} as CSSProperties;

export function AppAlertsProvider({
    children,
}: {
    children?: ReactNode;
}): ReactNode {
    const { appearance } = useAppearance();

    useEffect(() => {
        const removeFlashListener = router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!isFlashToast(data)) {
                return;
            }

            showAppAlert(data);
        });

        const removeHttpExceptionListener = router.on(
            'httpException',
            (event) => {
                const { response } = event.detail;

                showBackendErrorAlert(response);

                return false;
            },
        );

        const removeNetworkErrorListener = router.on(
            'networkError',
            (event) => {
                showAppAlert({
                    type: 'error',
                    title: 'Error de conexión',
                    description: extractBackendErrorMessage(
                        event.detail.error,
                        'No se pudo conectar con el servidor.',
                    ),
                    duration: 6000,
                });

                return false;
            },
        );

        return () => {
            removeFlashListener();
            removeHttpExceptionListener();
            removeNetworkErrorListener();
        };
    }, []);

    return (
        <>
            {children}
            <Sonner
                theme={appearance}
                position="top-right"
                duration={4500}
                visibleToasts={4}
                gap={10}
                closeButton
                className="app-alerts"
                toastOptions={{
                    className: 'app-alert',
                    duration: 4500,
                }}
                style={toasterStyle}
                containerAriaLabel="Notificaciones"
            />
        </>
    );
}

export function showAppAlert({
    type = 'info',
    title,
    message,
    description,
    className,
    duration,
    ...options
}: AppAlertOptions): void {
    const resolvedTitle = title ?? message ?? defaultAlertTitle(type);
    const resolvedDescription = description ?? (title ? message : undefined);
    const alertOptions: ExternalToast = {
        ...options,
        className: ['app-alert', `app-alert-${type}`, className]
            .filter(Boolean)
            .join(' '),
        description: resolvedDescription,
        duration: duration ?? 4500,
    };

    const toastMethods = {
        success: toast.success,
        info: toast.info,
        warning: toast.warning,
        error: toast.error,
    };

    toastMethods[type](resolvedTitle, alertOptions);
}

export function showBackendErrorAlert(
    error: unknown,
    options: BackendErrorAlertOptions = {},
): void {
    const { fallback, ...alertOptions } = options;

    showAppAlert({
        ...alertOptions,
        type: 'error',
        title: options.title ?? 'No se pudo completar la acción',
        description: getBackendErrorMessage(error, fallback),
        duration: alertOptions.duration ?? 6000,
    });
}

export function getBackendErrorMessage(
    error: unknown,
    fallback = DEFAULT_BACKEND_ERROR_MESSAGE,
): string {
    if (error !== null && error !== undefined) {
        logProductionError(error);
    }

    const messages = collectErrorMessages(error);
    const uniqueMessages = Array.from(
        new Set(messages.map((item) => item.trim())),
    )
        .filter(Boolean)
        .slice(0, 10);

    if (uniqueMessages.length === 0) {
        return fallback;
    }

    return uniqueMessages.join('\n');
}

export function extractBackendErrorMessage(
    error: unknown,
    fallback = DEFAULT_BACKEND_ERROR_MESSAGE,
): string {
    return getBackendErrorMessage(error, fallback);
}

function collectErrorMessages(
    value: unknown,
    label?: string,
    visited = new WeakSet<object>(),
): string[] {
    if (value === null || value === undefined) {
        return [];
    }

    if (typeof value === 'string') {
        return collectStringError(value, label);
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return [formatMessage(String(value), label)];
    }

    if (value instanceof Error) {
        if (visited.has(value)) {
            return [];
        }

        visited.add(value);

        const errorWithResponse = value as Error & { response?: unknown };
        const responseMessages = collectErrorMessages(
            errorWithResponse.response,
            label,
            visited,
        );
        const causeMessages = collectErrorMessages(value.cause, label, visited);

        if (responseMessages.length > 0 || causeMessages.length > 0) {
            return [...responseMessages, ...causeMessages];
        }

        if ('response' in errorWithResponse) {
            return [];
        }

        return value.message ? [formatMessage(value.message, label)] : [];
    }

    if (typeof value !== 'object') {
        return [];
    }

    if (
        (typeof Blob !== 'undefined' && value instanceof Blob) ||
        (typeof File !== 'undefined' && value instanceof File)
    ) {
        return [];
    }

    if (visited.has(value)) {
        return [];
    }

    visited.add(value);

    if (Array.isArray(value)) {
        return value.flatMap((item) =>
            collectErrorMessages(item, label, visited),
        );
    }

    const record = value as Record<string, unknown>;
    const prioritizedMessages = ERROR_MESSAGE_KEYS.flatMap((key) => {
        if (!(key in record)) {
            return [];
        }

        return collectErrorMessages(record[key], label, visited);
    });

    if (prioritizedMessages.length > 0) {
        return prioritizedMessages;
    }

    return Object.entries(record)
        .filter(([key]) => !IGNORED_ERROR_KEYS.has(key))
        .flatMap(([key, item]) =>
            collectErrorMessages(item, label ?? key, visited),
        );
}

function collectStringError(value: string, label?: string): string[] {
    if (isHtmlDocument(value)) {
        return [];
    }

    const trimmed = stripHtml(value).trim();

    if (!trimmed) {
        return [];
    }

    try {
        const parsed = JSON.parse(trimmed) as unknown;

        return collectErrorMessages(parsed, label);
    } catch {
        return [formatMessage(trimmed, label)];
    }
}

function logProductionError(error: unknown): void {
    if (import.meta.env.PROD || import.meta.env.MODE === 'production') {
        console.error('[Backend error]', error);
    }
}

function isHtmlDocument(value: string): boolean {
    return /<!doctype\s+html|<html[\s>]|<head[\s>]|<body[\s>]/i.test(value);
}

function formatMessage(message: string, label?: string): string {
    if (!label) {
        return message;
    }

    return `${label}: ${message}`;
}

function stripHtml(value: string): string {
    return value
        .replace(/<script[\s\S]*?<\/script>/gi, ' ')
        .replace(/<style[\s\S]*?<\/style>/gi, ' ')
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ');
}

function defaultAlertTitle(type: AppAlertType): string {
    const titles: Record<AppAlertType, string> = {
        error: 'Error',
        info: 'Información',
        success: 'Listo',
        warning: 'Atención',
    };

    return titles[type];
}

function isFlashToast(value: unknown): value is FlashToast {
    if (value === null || typeof value !== 'object') {
        return false;
    }

    const flashToast = value as Partial<FlashToast>;

    return (
        typeof flashToast.message === 'string' &&
        typeof flashToast.type === 'string' &&
        ['success', 'info', 'warning', 'error'].includes(flashToast.type)
    );
}
