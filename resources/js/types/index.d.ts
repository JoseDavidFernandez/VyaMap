import { PageProps as InertiaPageProps } from '@inertiajs/core';

interface AuthUser {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    // Cuenta de demostración de solo lectura: la interfaz oculta las escrituras.
    is_demo: boolean;
}

interface AuthProps {
    user: AuthUser | null;
}

interface FlashProps {
    // Aviso del servidor al rechazar una escritura de la cuenta de demostración.
    demoNotice: string | null;
}

interface FeatureFlags {
    // Solo oculta los campos de archivo: el servidor aplica la restricción.
    uploads: boolean;
    // Si la cuenta tiene fotos ya subidas (muestra el apartado Photos).
    photos: boolean;
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps {
        auth: AuthProps;
        features: FeatureFlags;
        flash: FlashProps;
    }
}