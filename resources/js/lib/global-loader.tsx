import { router } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import type { ReactNode } from 'react';

type LoaderEntry = {
    id: symbol;
    message: string;
};

export type GlobalLoaderContextValue = {
    hideLoader: (id: symbol) => void;
    isLoading: boolean;
    showLoader: (message?: string) => symbol;
    withLoader: <T>(
        task: Promise<T> | (() => T | Promise<T>),
        message?: string,
    ) => Promise<T>;
};

const GlobalLoaderContext = createContext<GlobalLoaderContextValue | null>(
    null,
);

const DEFAULT_LOADER_MESSAGE = 'Procesando cambios...';
const DEFAULT_READ_MESSAGE = 'Cargando información...';

export function GlobalLoaderProvider({ children }: { children: ReactNode }) {
    const [entries, setEntries] = useState<LoaderEntry[]>([]);
    const inertiaLoaders = useRef(new Map<string, symbol>());

    const showLoader = useCallback(
        (message = DEFAULT_LOADER_MESSAGE): symbol => {
            const id = Symbol('global-loader');

            setEntries((current) => [...current, { id, message }]);

            return id;
        },
        [],
    );

    const hideLoader = useCallback((id: symbol): void => {
        setEntries((current) => current.filter((entry) => entry.id !== id));
    }, []);

    const withLoader = useCallback(
        async <T,>(
            task: Promise<T> | (() => T | Promise<T>),
            message = DEFAULT_LOADER_MESSAGE,
        ): Promise<T> => {
            const id = showLoader(message);

            try {
                return await (typeof task === 'function' ? task() : task);
            } finally {
                hideLoader(id);
            }
        },
        [hideLoader, showLoader],
    );

    const value = useMemo<GlobalLoaderContextValue>(
        () => ({
            hideLoader,
            isLoading: entries.length > 0,
            showLoader,
            withLoader,
        }),
        [entries.length, hideLoader, showLoader, withLoader],
    );

    useEffect(() => {
        const activeInertiaLoaders = inertiaLoaders.current;
        const removeStartListener = router.on('start', (event) => {
            const visit = event.detail.visit;

            if (visit.prefetch) {
                return;
            }

            const id = showLoader(messageForMethod(visit.method));

            inertiaLoaders.current.set(visit.id, id);
        });

        const removeFinishListener = router.on('finish', (event) => {
            const visitId = event.detail.visit.id;
            const id = inertiaLoaders.current.get(visitId);

            if (!id) {
                return;
            }

            inertiaLoaders.current.delete(visitId);
            hideLoader(id);
        });

        const restoreNetworkTracking = installNetworkTracking(
            showLoader,
            hideLoader,
        );

        return () => {
            removeStartListener();
            removeFinishListener();
            restoreNetworkTracking();

            for (const id of activeInertiaLoaders.values()) {
                hideLoader(id);
            }

            activeInertiaLoaders.clear();
        };
    }, [hideLoader, showLoader]);

    useEffect(() => {
        if (entries.length > 0) {
            document.body.setAttribute('aria-busy', 'true');
        } else {
            document.body.removeAttribute('aria-busy');
        }

        return () => document.body.removeAttribute('aria-busy');
    }, [entries.length]);

    const activeEntry = entries[entries.length - 1];

    return (
        <GlobalLoaderContext.Provider value={value}>
            {children}
            {activeEntry && (
                <div
                    className="global-page-loader"
                    role="status"
                    aria-live="polite"
                    aria-label={activeEntry.message}
                >
                    <div className="global-page-loader-card">
                        <LoaderCircle
                            className="global-page-loader-icon"
                            aria-hidden="true"
                        />
                        <span>{activeEntry.message}</span>
                    </div>
                </div>
            )}
        </GlobalLoaderContext.Provider>
    );
}

export function useGlobalLoader(): GlobalLoaderContextValue {
    const context = useContext(GlobalLoaderContext);

    if (!context) {
        throw new Error(
            'useGlobalLoader debe usarse dentro de GlobalLoaderProvider.',
        );
    }

    return context;
}

export function useGlobalLoading(
    isLoading: boolean,
    message = DEFAULT_LOADER_MESSAGE,
): void {
    const { hideLoader, showLoader } = useGlobalLoader();

    useEffect(() => {
        if (!isLoading) {
            return undefined;
        }

        const id = showLoader(message);

        return () => hideLoader(id);
    }, [hideLoader, isLoading, message, showLoader]);
}

function installNetworkTracking(
    showLoader: (message?: string) => symbol,
    hideLoader: (id: symbol) => void,
): () => void {
    if (typeof window === 'undefined') {
        return () => undefined;
    }

    const originalFetch = window.fetch;
    const originalOpen = XMLHttpRequest.prototype.open as XhrOpen;
    const originalSend = XMLHttpRequest.prototype.send as XhrSend;
    const originalSetRequestHeader = XMLHttpRequest.prototype
        .setRequestHeader as XhrSetRequestHeader;
    const xhrRequests = new WeakMap<XMLHttpRequest, XhrRequestMetadata>();

    window.fetch = async (
        ...args: Parameters<typeof fetch>
    ): Promise<Response> => {
        const [input, init] = args;
        const method =
            init?.method ?? (input instanceof Request ? input.method : 'GET');
        const url = input instanceof Request ? input.url : String(input);

        if (!shouldTrackRequest(url)) {
            return originalFetch(...args);
        }

        const id = showLoader(messageForMethod(method));

        try {
            return await originalFetch(...args);
        } finally {
            hideLoader(id);
        }
    };

    XMLHttpRequest.prototype.open = function (
        method: string,
        url: string | URL,
        async = true,
        username?: string | null,
        password?: string | null,
    ): void {
        xhrRequests.set(this, {
            isInertia: false,
            method,
            url: String(url),
        });

        Reflect.apply(originalOpen, this, [
            method,
            url,
            async,
            username,
            password,
        ]);
    };

    XMLHttpRequest.prototype.setRequestHeader = function (
        name: string,
        value: string,
    ): void {
        const request = xhrRequests.get(this);

        if (request && name.toLowerCase() === 'x-inertia') {
            request.isInertia = true;
        }

        originalSetRequestHeader.call(this, name, value);
    };

    XMLHttpRequest.prototype.send = function (
        body?: Parameters<XMLHttpRequest['send']>[0],
    ): void {
        const request = xhrRequests.get(this);

        if (!request || request.isInertia || !shouldTrackRequest(request.url)) {
            originalSend.call(this, body);

            return;
        }

        const id = showLoader(messageForMethod(request.method));
        let released = false;
        const release = (): void => {
            if (released) {
                return;
            }

            released = true;
            hideLoader(id);
        };

        this.addEventListener('loadend', release, { once: true });

        try {
            originalSend.call(this, body);
        } catch (error) {
            release();

            throw error;
        }
    };

    return () => {
        window.fetch = originalFetch;
        XMLHttpRequest.prototype.open = originalOpen;
        XMLHttpRequest.prototype.send = originalSend;
        XMLHttpRequest.prototype.setRequestHeader = originalSetRequestHeader;
    };
}

type XhrOpen = (
    method: string,
    url: string | URL,
    async?: boolean,
    username?: string | null,
    password?: string | null,
) => void;

type XhrSend = (body?: Parameters<XMLHttpRequest['send']>[0]) => void;

type XhrSetRequestHeader = (name: string, value: string) => void;

type XhrRequestMetadata = {
    isInertia: boolean;
    method: string;
    url: string;
};

function shouldTrackRequest(url: string): boolean {
    try {
        return (
            new URL(url, window.location.href).origin === window.location.origin
        );
    } catch {
        return false;
    }
}

function messageForMethod(method: string): string {
    return method.toLowerCase() === 'get'
        ? DEFAULT_READ_MESSAGE
        : DEFAULT_LOADER_MESSAGE;
}
