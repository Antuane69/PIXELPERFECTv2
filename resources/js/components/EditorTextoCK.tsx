'use client';

import React, { useEffect, useRef, useState } from 'react';
import './EditorTextoCK.css';

export interface CKEditorInstance {
    on: (eventName: string, listener: (event?: CKEditorEvent) => void) => void;
    off?: (
        eventName: string,
        listener: (event?: CKEditorEvent) => void,
    ) => void;
    getCommand?: (commandName: string) => CKEditorCommand | undefined;
    ui?: {
        get: (itemName: string) => CKEditorUiItem | undefined;
    };
    getData: () => string;
    setData: (data: string) => void;
    focus: () => void;
    insertText: (text: string) => void;
    insertHtml: (html: string) => void;
    destroy: () => void;
}

interface CKEditorCommand {
    state?: number;
    startDisabled?: boolean;
    disable?: () => void;
    enable?: () => void;
}

interface CKEditorUiItem {
    setState?: (state: number) => void;
}

interface ClipboardItemLike {
    kind?: string;
    getAsFile?: () => Blob | null;
}

interface NativeClipboardDataLike {
    files?: { length: number; [index: number]: Blob };
    items?: { length: number; [index: number]: ClipboardItemLike };
}

interface CKEditorDataTransfer {
    getData?: (type: string, encode?: boolean) => unknown;
    getFilesCount?: () => number;
    getFile?: (index: number) => unknown;
    $?: NativeClipboardDataLike;
}

interface CKEditorEvent {
    data?: {
        dataValue?: unknown;
        dataTransfer?: CKEditorDataTransfer;
        $?: { clipboardData?: NativeClipboardDataLike };
        type?: string;
    };
    cancel?: () => void;
}

interface CKEditorGlobal {
    replace: (
        element: HTMLElement,
        config?: Record<string, unknown>,
    ) => CKEditorInstance;
}

interface EditorTextoCKInterface {
    setContent: React.Dispatch<React.SetStateAction<string>>;
    editorRef: React.MutableRefObject<HTMLTextAreaElement | null>;
    ckEditorRef: React.MutableRefObject<CKEditorInstance | null>;
    initialContent?: string;
    ariaLabel?: string;
    widthProp?: number | string;
    maxWidthProp?: string;
    heightProp?: string | number;
    autoHeight?: boolean;
    onReady?: () => void;
    uploadUrl?: string;
    uploadHeaders?: Record<string, string>;
    /** Botones CKEditor que deben ocultarse. Ejemplo: ['Image', 'Link']. */
    removeButtons?: string | string[];
    /** Botones CKEditor que deben permanecer visibles, pero deshabilitados. */
    disabledButtons?: string[];
}

declare global {
    interface Window {
        CKEDITOR?: CKEditorGlobal;
    }
}

const scriptPromises = new Map<string, Promise<CKEditorGlobal>>();
const CKEDITOR_TRISTATE_DISABLED = 0;
const CKEDITOR_TRISTATE_OFF = 2;

function getCKEditorBaseUrl(): string {
    const configuredBaseUrl = import.meta.env.VITE_CKEDITOR_BASE_URL?.trim();

    if (configuredBaseUrl) {
        return configuredBaseUrl.replace(/\/+$/, '');
    }

    // CKEditor carga plugins y estilos relativos desde public/js/ckeditor.
    // Se puede apuntar a otro host con VITE_CKEDITOR_BASE_URL si hace falta.
    return '';
}

function loadCKEditor(scriptUrl: string): Promise<CKEditorGlobal> {
    if (window.CKEDITOR) {
        return Promise.resolve(window.CKEDITOR);
    }

    const existingPromise = scriptPromises.get(scriptUrl);

    if (existingPromise) {
        return existingPromise;
    }

    const promise = new Promise<CKEditorGlobal>((resolve, reject) => {
        const existingScript = document.querySelector<HTMLScriptElement>(
            `script[data-editor-texto-ck="${scriptUrl}"]`,
        );
        const script = existingScript ?? document.createElement('script');

        const handleLoad = () => {
            if (window.CKEDITOR) {
                resolve(window.CKEDITOR);
            } else {
                reject(
                    new Error(
                        'CKEditor cargó, pero no expuso la instancia global.',
                    ),
                );
            }
        };

        const handleError = () =>
            reject(new Error(`No se pudo cargar CKEditor desde ${scriptUrl}.`));

        script.addEventListener('load', handleLoad, { once: true });
        script.addEventListener('error', handleError, { once: true });

        if (!existingScript) {
            script.async = true;
            script.src = scriptUrl;
            script.dataset.editorTextoCk = scriptUrl;
            document.head.appendChild(script);
        }
    });

    scriptPromises.set(scriptUrl, promise);
    promise.catch(() => {
        if (scriptPromises.get(scriptUrl) === promise) {
            scriptPromises.delete(scriptUrl);
        }
    });

    return promise;
}

export function preloadCKEditor(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const baseUrl = getCKEditorBaseUrl();
    void loadCKEditor(`${baseUrl}/js/ckeditor/ckeditor.js`).catch(() => {
        // La inicialización real volverá a intentar la carga cuando el editor se abra.
    });
}

function getClipboardFiles(event?: CKEditorEvent): Blob[] {
    const files: Blob[] = [];
    const seen = new Set<unknown>();
    const addFile = (file: unknown) => {
        if (
            !file ||
            typeof (file as { type?: unknown }).type !== 'string' ||
            seen.has(file)
        ) {
            return;
        }

        seen.add(file);
        files.push(file as Blob);
    };

    const dataTransfer = event?.data?.dataTransfer;

    if (dataTransfer?.getFilesCount && dataTransfer?.getFile) {
        const count = Number(dataTransfer.getFilesCount()) || 0;

        for (let index = 0; index < count; index += 1) {
            addFile(dataTransfer.getFile(index));
        }
    }

    const nativeDataTransfer = dataTransfer?.$ ?? event?.data?.$?.clipboardData;

    if (nativeDataTransfer?.files) {
        for (
            let index = 0;
            index < nativeDataTransfer.files.length;
            index += 1
        ) {
            addFile(nativeDataTransfer.files[index]);
        }
    }

    if (nativeDataTransfer?.items) {
        for (
            let index = 0;
            index < nativeDataTransfer.items.length;
            index += 1
        ) {
            const item = nativeDataTransfer.items[index];

            if (item?.kind === 'file' && typeof item.getAsFile === 'function') {
                addFile(item.getAsFile());
            }
        }
    }

    return files.filter((file) => file.type.toLowerCase().startsWith('image/'));
}

function getClipboardMarkup(event?: CKEditorEvent): string {
    const dataValue = event?.data?.dataValue;

    if (typeof dataValue === 'string' && dataValue.trim()) {
        return dataValue;
    }

    const html = event?.data?.dataTransfer?.getData?.('text/html');

    return typeof html === 'string' ? html : '';
}

function readAsDataUrl(file: Blob): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result));
        reader.onerror = () =>
            reject(
                reader.error ?? new Error('No se pudo leer la imagen pegada.'),
            );
        reader.readAsDataURL(file);
    });
}

function escapeHtmlAttribute(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function normalizeButtonNames(
    buttons: string | string[] | undefined,
): string[] {
    const values = Array.isArray(buttons)
        ? buttons
        : (buttons?.split(',') ?? []);

    return Array.from(
        new Set(values.map((button) => button.trim()).filter(Boolean)),
    );
}

function getButtonLookupNames(buttonName: string): string[] {
    const normalizedName = buttonName.trim();

    if (!normalizedName) {
        return [];
    }

    return Array.from(
        new Set([
            normalizedName,
            normalizedName.charAt(0).toUpperCase() + normalizedName.slice(1),
            normalizedName.toLowerCase(),
        ]),
    );
}

function getEditorUiItem(
    instance: CKEditorInstance,
    buttonName: string,
): CKEditorUiItem | undefined {
    for (const lookupName of getButtonLookupNames(buttonName)) {
        const item = instance.ui?.get(lookupName);

        if (item) {
            return item;
        }
    }

    return undefined;
}

function getEditorCommand(
    instance: CKEditorInstance,
    buttonName: string,
): CKEditorCommand | undefined {
    if (!instance.getCommand) {
        return undefined;
    }

    for (const lookupName of getButtonLookupNames(buttonName)) {
        const command = instance.getCommand(lookupName);

        if (command) {
            return command;
        }
    }

    return undefined;
}

function applyDisabledButtons(
    instance: CKEditorInstance,
    disabledButtons: string[],
    appliedButtonsRef: React.MutableRefObject<string[]>,
): void {
    const nextButtons = normalizeButtonNames(disabledButtons);
    const nextButtonSet = new Set(
        nextButtons.map((button) => button.toLowerCase()),
    );

    appliedButtonsRef.current
        .filter((button) => !nextButtonSet.has(button.toLowerCase()))
        .forEach((button) => {
            const command = getEditorCommand(instance, button);

            if (command) {
                command.startDisabled = false;
                command.enable?.();
            }

            const uiItem = getEditorUiItem(instance, button);
            uiItem?.setState?.(command?.state ?? CKEDITOR_TRISTATE_OFF);
        });

    nextButtons.forEach((button) => {
        const command = getEditorCommand(instance, button);

        if (command) {
            command.startDisabled = true;
            command.disable?.();
        }

        getEditorUiItem(instance, button)?.setState?.(
            CKEDITOR_TRISTATE_DISABLED,
        );
    });

    appliedButtonsRef.current = nextButtons;
}

export default function EditorTextoCK({
    setContent,
    editorRef,
    ckEditorRef,
    initialContent = '',
    ariaLabel = 'Descripción',
    widthProp = '700px',
    maxWidthProp = '100%',
    heightProp = '200px',
    autoHeight = false,
    onReady,
    uploadUrl,
    uploadHeaders,
    removeButtons,
    disabledButtons = [],
}: EditorTextoCKInterface) {
    const [loadError, setLoadError] = useState<string | null>(null);
    const latestContentRef = useRef(initialContent);
    const onReadyRef = useRef(onReady);
    const desiredDisabledButtonsRef = useRef(disabledButtons);
    const appliedDisabledButtonsRef = useRef<string[]>([]);
    const removeButtonsKey = normalizeButtonNames(removeButtons).join(',');
    const disabledButtonsKey = normalizeButtonNames(disabledButtons).join(',');

    useEffect(() => {
        onReadyRef.current = onReady;
    }, [onReady]);

    useEffect(() => {
        desiredDisabledButtonsRef.current = disabledButtons;
    }, [disabledButtons]);

    useEffect(() => {
        latestContentRef.current = initialContent;
    }, [initialContent]);

    useEffect(() => {
        let mounted = true;
        const textarea = editorRef.current;

        if (!textarea) {
            return undefined;
        }

        const baseUrl = getCKEditorBaseUrl();
        const scriptUrl = `${baseUrl}/js/ckeditor/ckeditor.js`;
        const contentsCssUrl = `${baseUrl}/js/ckeditor/css/style.css`;
        const height =
            typeof heightProp === 'number'
                ? heightProp
                : Number.parseInt(heightProp, 10) || 200;
        let createdInstance: CKEditorInstance | null = null;

        loadCKEditor(scriptUrl)
            .then((CKEDITOR) => {
                if (!mounted || !editorRef.current) {
                    return;
                }

                const extraPlugins = autoHeight ? 'font,autogrow' : 'font';
                const removePlugins = [
                    'print',
                    'preview',
                    'tabletoolstoolbar',
                    'tableselection',
                    'tabletools',
                    'table',
                    'tableresize',
                    'tableresizerowandcolumn',
                    ...(autoHeight ? [] : ['autogrow']),
                    ...(uploadUrl
                        ? []
                        : ['uploadfile', 'uploadimage', 'uploadwidget']),
                ].join(',');

                const config: Record<string, unknown> = {
                    customConfig: '',
                    bodyClass: 'body-overflow',
                    contentsCss: [contentsCssUrl],
                    language: 'es-mx',
                    // Word uses inline styles for paragraph indentation, list layout,
                    // table dimensions and image positioning. Keep those supported
                    // presentation styles while CKEditor continues filtering elements
                    // and attributes not listed here.
                    extraAllowedContent: [
                        'p div h1 h2 h3 h4 h5 h6 pre{margin,margin-*,text-indent,text-align,line-height,font-family,font-size,font-weight,font-style,text-decoration,color,background,background-color,vertical-align,white-space,direction}',
                        'span{font-family,font-size,font-weight,font-style,text-decoration,color,background,background-color,vertical-align,white-space}',
                        'ol ul li{margin,margin-*,padding,padding-*,text-indent,text-align,line-height,list-style,list-style-type,list-style-position,vertical-align}',
                        'ol{list-style-type,list-style-position}[start,type]',
                        'li{list-style-type,list-style-position}[value]',
                        'table{width,height,margin,margin-*,padding,padding-*,border,border-*,border-collapse,background,background-color,vertical-align,text-align}',
                        'thead tbody tfoot tr{height,background,background-color,vertical-align,text-align}',
                        'td th{width,height,margin,margin-*,padding,padding-*,border,border-*,background,background-color,vertical-align,text-align,white-space}',
                        'img[!src,alt,width,height,align]{width,height,max-width,max-height,float,display,margin,margin-*,vertical-align,border,border-*,background,background-color}',
                        'figure figcaption{width,height,margin,margin-*,padding,padding-*,border,border-*,background,background-color,text-align,vertical-align}',
                    ].join(';'),
                    pasteFromWordPromptCleanup: false,
                    pasteFromWordRemoveFontStyles: false,
                    pasteFromWord_keepZeroMargins: true,
                    pasteFromWord_inlineImages: true,
                    toolbarGroups: [
                        {
                            name: 'document',
                            groups: ['mode', 'document', 'doctools'],
                        },
                        { name: 'clipboard', groups: ['clipboard', 'undo'] },
                        {
                            name: 'editing',
                            groups: ['find', 'selection', 'spellchecker'],
                        },
                        { name: 'forms' },
                        {
                            name: 'basicstyles',
                            groups: ['basicstyles', 'cleanup'],
                        },
                        {
                            name: 'paragraph',
                            groups: [
                                'list',
                                'indent',
                                'blocks',
                                'align',
                                'bidi',
                            ],
                        },
                        { name: 'links' },
                        { name: 'insert' },
                        { name: 'styles' },
                        { name: 'colors' },
                        { name: 'tools' },
                        { name: 'others' },
                        { name: 'about' },
                    ],
                    removeDialogTabs: 'link:advanced',
                    removePlugins,
                    extraPlugins,
                    removeButtons: removeButtonsKey,
                    height,
                    ...(autoHeight
                        ? {
                              autoGrow_onStartup: true,
                              autoGrow_minHeight: height,
                              autoGrow_maxHeight: 0,
                          }
                        : {}),
                    ...(uploadUrl
                        ? {
                              fileTools_requestHeaders: uploadHeaders ?? {},
                              uploadUrl,
                              imageUploadUrl: uploadUrl,
                              filebrowserUploadUrl: uploadUrl,
                          }
                        : {}),
                };

                try {
                    createdInstance = CKEDITOR.replace(
                        editorRef.current,
                        config,
                    );
                } catch (error) {
                    setLoadError(
                        error instanceof Error
                            ? error.message
                            : 'No se pudo inicializar CKEditor.',
                    );

                    return;
                }

                const instance = createdInstance;

                if (!instance) {
                    return;
                }

                const handleChange = () => {
                    const data = instance.getData();
                    latestContentRef.current = data;
                    setContent(data);
                };
                const handlePaste = (event?: CKEditorEvent) => {
                    const imageFiles = getClipboardFiles(event);

                    // Word exposes embedded images as clipboard files in some browsers,
                    // but also supplies HTML/RTF containing their original position.
                    // Let pastefromword process that structured payload; use data URIs
                    // only for a standalone image paste with no text or HTML payload.
                    if (
                        imageFiles.length &&
                        !getClipboardMarkup(event).trim()
                    ) {
                        // CKEditor 4 detecta el archivo, pero sin un endpoint de upload no
                        // lo inserta por sí solo. Lo convertimos a data URI para conservar
                        // la imagen inline dentro del HTML que devuelve getData().
                        event?.cancel?.();

                        Promise.all(imageFiles.map(readAsDataUrl))
                            .then((dataUrls) => {
                                if (!mounted) {
                                    return;
                                }

                                const images = dataUrls
                                    .map(
                                        (dataUrl) =>
                                            `<img src="${escapeHtmlAttribute(dataUrl)}" alt="" />`,
                                    )
                                    .join('<br>');

                                instance.insertHtml(images);
                                const data = instance.getData();
                                latestContentRef.current = data;
                                setContent(data);
                            })
                            .catch((error: unknown) => {
                                console.error(
                                    'No se pudo insertar la imagen pegada en CKEditor.',
                                    error,
                                );
                            });

                        return;
                    }
                };
                const handleReady = () => {
                    if (!mounted) {
                        return;
                    }

                    ckEditorRef.current = instance;
                    instance.setData(latestContentRef.current ?? '');
                    applyDisabledButtons(
                        instance,
                        desiredDisabledButtonsRef.current,
                        appliedDisabledButtonsRef,
                    );
                    instance.on('change', handleChange);
                    instance.on('paste', handlePaste);
                    onReadyRef.current?.();
                };

                const handleSelectionChange = () => {
                    applyDisabledButtons(
                        instance,
                        desiredDisabledButtonsRef.current,
                        appliedDisabledButtonsRef,
                    );
                };

                instance.on('instanceReady', handleReady);
                instance.on('selectionChange', handleSelectionChange);
            })
            .catch((error: unknown) => {
                if (!mounted) {
                    return;
                }

                setLoadError(
                    error instanceof Error
                        ? error.message
                        : 'No se pudo cargar CKEditor.',
                );
            });

        return () => {
            mounted = false;
            const instance = ckEditorRef.current ?? createdInstance;

            if (instance) {
                try {
                    latestContentRef.current = instance.getData();
                    instance.destroy();
                } catch {
                    // CKEditor puede haber sido destruido por el ciclo de vida del Drawer.
                }
            }

            ckEditorRef.current = null;
        };
        // Las refs, setContent y callback actual son estables; editor debe inicializarse una sola vez por montaje.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        autoHeight,
        editorRef,
        heightProp,
        removeButtonsKey,
        setContent,
        uploadHeaders,
        uploadUrl,
    ]);

    useEffect(() => {
        const instance = ckEditorRef.current;

        if (!instance) {
            return;
        }

        applyDisabledButtons(
            instance,
            desiredDisabledButtonsRef.current,
            appliedDisabledButtonsRef,
        );
    }, [ckEditorRef, disabledButtonsKey]);

    useEffect(() => {
        const instance = ckEditorRef.current;

        if (!instance || initialContent === undefined) {
            return;
        }

        if (instance.getData() !== initialContent) {
            instance.setData(initialContent);
        }
    }, [ckEditorRef, initialContent]);

    return (
        <div
            className="editor-texto-ck"
            style={{ width: widthProp, maxWidth: maxWidthProp }}
        >
            <textarea
                ref={editorRef}
                defaultValue={initialContent}
                aria-label={ariaLabel}
            />
            {loadError && (
                <div className="editor-texto-ck__error" role="alert">
                    No se pudo cargar el editor de texto.
                </div>
            )}
        </div>
    );
}
