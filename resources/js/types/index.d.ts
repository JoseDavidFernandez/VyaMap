import { PageProps as InertiaPageProps } from '@inertiajs/core';

interface AuthUser {
    id: number;
    name: string;
    email: string;
    avatar: string | null;

}

interface AuthProps {
    user: AuthUser | null;
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
    }
}