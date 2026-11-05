import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

/*
 * Cuenta de demostración de solo lectura: la banda de AppLayout y el botón
 * «Try the demo» del login. El bloqueo real está en el servidor
 * (tests/Feature/DemoAccountTest.php).
 */

const page = reactive({
    url: '/',
    props: {
        auth: { user: null as null | { is_demo: boolean } },
        flash: { demoNotice: null as string | null },
        features: { uploads: false, photos: false },
    },
});

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => page,
    // Sin aplicación Inertia no hay gestor de <head>.
    Head: { render: () => null },
}));

const stubs = { Navbar: true, Head: true, Link: { template: '<a><slot /></a>' } };

describe('AppLayout', () => {
    beforeEach(() => {
        page.props.flash.demoNotice = null;
    });

    it('shows the read-only banner only to the demo account', async () => {
        const { default: AppLayout } = await import('../../resources/js/layouts/AppLayout.vue');

        page.props.auth.user = { is_demo: false };
        expect(mount(AppLayout, { global: { stubs } }).find('[data-demo-banner]').exists()).toBe(false);

        page.props.auth.user = { is_demo: true };
        const banner = mount(AppLayout, { global: { stubs } }).get('[data-demo-banner] p');
        expect(banner.text()).toContain('This is a read-only demo account.');
        expect(banner.attributes('role')).toBe('status');
    });

    it('turns the banner into an alert when the server rejects a change', async () => {
        const { default: AppLayout } = await import('../../resources/js/layouts/AppLayout.vue');

        page.props.auth.user = { is_demo: true };
        page.props.flash.demoNotice = 'This is a read-only demo account.';

        expect(mount(AppLayout, { global: { stubs } }).get('[data-demo-banner] p').attributes('role')).toBe('alert');
    });
});

describe('Navbar', () => {
    it('hides «New trip» from the demo account', async () => {
        const { default: Navbar } = await import('../../resources/js/components/Navbar.vue');
        const newTripLinks = () => mount(Navbar, { global: { stubs: { Link: { template: '<a v-bind="$attrs"><slot /></a>' } } } })
            .findAll('a').filter((link) => link.attributes('href') === '/trips/create').length;

        page.props.auth.user = { is_demo: false };
        expect(newTripLinks()).toBeGreaterThan(0);

        page.props.auth.user = { is_demo: true };
        expect(newTripLinks()).toBe(0);
    });
});

describe('Login', () => {
    it('offers «Try the demo» only when the server says there is a demo account', async () => {
        const { default: Login } = await import('../../resources/js/pages/Auth/Login.vue');

        const hasDemo = (canTryDemo: boolean) => mount(Login, { props: { canTryDemo }, global: { stubs } })
            .findAll('button').some((button) => button.text() === 'Try the demo');

        expect(hasDemo(true)).toBe(true);
        expect(hasDemo(false)).toBe(false);
    });

    it('signs in to the demo without sending any credential', async () => {
        const { default: Login } = await import('../../resources/js/pages/Auth/Login.vue');
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});

        const wrapper = mount(Login, { props: { canTryDemo: true }, global: { stubs } });
        await wrapper.findAll('button').find((button) => button.text() === 'Try the demo')!.trigger('click');

        expect(visit).toHaveBeenCalledTimes(1);
        const [url, options] = visit.mock.calls[0] as [string, { method: string; data: Record<string, unknown> }];
        expect(url).toBe('/demo-login');
        expect(options.method).toBe('post');
        expect(options.data).toEqual({});
    });
});
