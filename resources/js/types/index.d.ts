import { PageProps as InertiaPageProps } from '@inertiajs/core';

interface AuthUser {
    id: number;
    name: string;
    email: string;
}

interface AuthProps {
    user: AuthUser | null;
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps {
        auth: AuthProps;
    }
}