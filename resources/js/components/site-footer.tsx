import { Link } from '@inertiajs/react';
import { House, Mail, MessageCircle } from 'lucide-react';
import { home } from '@/routes';

const contactMessage =
    'Hola, quiero hacer una cotización con Pixel Perfect. ¿Me pueden ayudar, por favor?';
const emailHref = `mailto:pixelperfect.nacif@gmail.com?subject=${encodeURIComponent('Solicitud de cotización')}&body=${encodeURIComponent(contactMessage)}`;
const whatsappHref = `https://wa.me/523221974630?text=${encodeURIComponent(contactMessage)}`;
const contactLinkClass =
    'flex size-8 items-center justify-center rounded-full border border-primary/20 bg-primary/10 text-primary transition-colors hover:border-primary/35 hover:bg-primary/20 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none';

export function SiteFooter() {
    const currentYear = new Date().getFullYear();

    return (
        <footer className="border-t border-border/70 bg-card/70 px-4 py-3 backdrop-blur-sm sm:px-6">
            <div className="mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-3 text-center sm:flex-row sm:text-left">
                <div className="grid gap-0.5">
                    <p className="text-sm font-semibold tracking-tight text-foreground">
                        Pixel Perfect
                    </p>
                    <p className="text-[11px] leading-4 text-muted-foreground">
                        Copyright © 2024 - {currentYear} PixelPerfect. Todos los
                        derechos reservados.
                    </p>
                    <p className="text-[11px] leading-4 text-muted-foreground">
                        La marca Pixel Perfect es una orgullosa start-up
                        mexicana.
                    </p>
                </div>

                <nav
                    className="flex items-center gap-2"
                    aria-label="Enlaces de contacto"
                >
                    <Link
                        href={home()}
                        className={contactLinkClass}
                        aria-label="Ir al inicio"
                        title="Inicio"
                    >
                        <House className="size-3.5" aria-hidden="true" />
                    </Link>
                    <a
                        href={emailHref}
                        className={contactLinkClass}
                        aria-label="Enviar correo a Pixel Perfect"
                        title="Correo electrónico"
                    >
                        <Mail className="size-3.5" aria-hidden="true" />
                    </a>
                    <a
                        href={whatsappHref}
                        target="_blank"
                        rel="noreferrer"
                        className={contactLinkClass}
                        aria-label="Contactar a Pixel Perfect por WhatsApp"
                        title="WhatsApp"
                    >
                        <MessageCircle
                            className="size-3.5"
                            aria-hidden="true"
                        />
                    </a>
                </nav>
            </div>
        </footer>
    );
}
