import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Cuenta de demostración de solo lectura: la interfaz oculta los controles de
 * escritura. Es solo presentación; el servidor rechaza cualquier escritura
 * (App\Http\Middleware\DemoReadOnly).
 */
export const useReadOnly = () => {
    const page = usePage();

    return computed(() => page.props.auth?.user?.is_demo === true);
};
