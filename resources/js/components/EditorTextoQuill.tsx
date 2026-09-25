'use client';
import type QuillType from 'quill';
import React, { useEffect } from 'react';
import 'quill/dist/quill.snow.css';
import './EditorTextoQuill.css';

interface EditorTextoInterface {
    setContent: React.Dispatch<React.SetStateAction<string>>;
    editorRef: React.MutableRefObject<any>;
    quillRef: React.MutableRefObject<any>;
    initialContent?: string;
    widthProp?: number | string;
    maxWidthProp?: string;
    heightProp?: string;
    autoHeight?: boolean;
    onReady?: () => void;
}

export default function EditorTextoQuill({
    setContent,
    editorRef,
    quillRef,
    initialContent = '',
    widthProp = '700px',
    maxWidthProp = '100%',
    heightProp = '200px',
    autoHeight = false,
    onReady,
}: EditorTextoInterface) {
    useEffect(() => {
        let mounted = true;
        let cleanupEditor: (() => void) | undefined;

        if (!editorRef?.current) {
            return;
        }

        // evita reinicializar
        if (editorRef.current.dataset?.quillInitialized) {
            // si ya inicializado, solo aplicar initialContent si llega nuevo
            if (quillRef.current && initialContent) {
                quillRef.current.clipboard.dangerouslyPasteHTML(initialContent);
            }

            return;
        }

        (async () => {
            const QuillModule = await import('quill');
            const Quill = (QuillModule as any).default ?? QuillModule;

            if (!mounted) {
                return;
            }

            const instance = new (Quill as typeof QuillType)(
                editorRef.current,
                {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ header: [1, 2, false] }],
                            ['bold', 'italic', 'underline'],
                            [{ list: 'ordered' }, { list: 'bullet' }],
                            ['link', 'image'],
                        ],
                    },
                },
            );

            editorRef.current.dataset.quillInitialized = 'true';
            quillRef.current = instance;

            // exponer root como tu editorRef (similar a lo que tenías)
            editorRef.current = instance.root;

            let resizeFrame: number | null = null;

            const adjustEditorHeight = () => {
                if (!autoHeight) {
                    return;
                }

                const root = instance.root as HTMLElement;
                const container = root.parentElement as HTMLElement | null;

                // Quill deja el editor con height: 100% y overflow-y: auto. Al
                // quitar esa restricción podemos medir todo el contenido, incluidas
                // las imágenes pegadas, y hacer crecer el editor con él.
                root.style.height = 'auto';
                root.style.minHeight = heightProp;
                root.style.overflowY = 'visible';

                if (container) {
                    container.style.height = 'auto';
                    container.style.minHeight = heightProp;
                    container.style.overflow = 'visible';
                }

                const contentHeight = Math.max(
                    root.scrollHeight,
                    root.offsetHeight,
                );
                root.style.height = `${contentHeight}px`;

                if (container) {
                    container.style.height = `${contentHeight}px`;
                }
            };

            const scheduleEditorHeight = () => {
                if (!autoHeight) {
                    return;
                }

                if (resizeFrame !== null) {
                    window.cancelAnimationFrame(resizeFrame);
                }

                resizeFrame = window.requestAnimationFrame(() => {
                    resizeFrame = null;
                    adjustEditorHeight();
                });
            };

            const handleTextChange = () => {
                setContent(instance.root.innerHTML);
                scheduleEditorHeight();
            };

            instance.on('text-change', handleTextChange);

            if (autoHeight) {
                // La imagen puede cambiar de tamaño después del text-change cuando
                // termina de cargar, por eso también recalculamos en ese momento.
                const handleImageLoad = () => scheduleEditorHeight();
                instance.root.addEventListener('load', handleImageLoad, true);

                const resizeObserver =
                    typeof ResizeObserver !== 'undefined'
                        ? new ResizeObserver(scheduleEditorHeight)
                        : null;

                resizeObserver?.observe(instance.root);
                cleanupEditor = () => {
                    instance.root.removeEventListener(
                        'load',
                        handleImageLoad,
                        true,
                    );
                    resizeObserver?.disconnect();

                    if (resizeFrame !== null) {
                        window.cancelAnimationFrame(resizeFrame);
                    }
                };
            }

            const cleanupTextChange = cleanupEditor;
            cleanupEditor = () => {
                instance.off('text-change', handleTextChange);
                cleanupTextChange?.();
            };

            if (initialContent) {
                instance.clipboard.dangerouslyPasteHTML(initialContent);
            }

            scheduleEditorHeight();
            onReady?.();
        })();

        return () => {
            mounted = false;
            cleanupEditor?.();

            if (quillRef.current) {
                quillRef.current = null;
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []); // montaje

    // Si initialContent cambia desde el padre
    useEffect(() => {
        if (quillRef.current && initialContent !== undefined) {
            const currentContent = quillRef.current.root.innerHTML;

            if (currentContent !== initialContent) {
                try {
                    quillRef.current.clipboard.dangerouslyPasteHTML(
                        initialContent,
                    );
                } catch {
                    // Conserva el contenido actual si el HTML inicial no puede importarse.
                }
            }
        }
    }, [initialContent, quillRef]);

    return (
        <div
            className="editor-texto-quill"
            style={{ width: widthProp, maxWidth: maxWidthProp }}
        >
            <div
                ref={editorRef}
                style={{ height: autoHeight ? 'auto' : heightProp }}
            />
        </div>
    );
}
