import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import type { FlightRecord } from '../../resources/js/types/flights';

/*
 * Vuelos existentes en un viaje:
 * - TripFlightSuggestions (crear y editar): aceptar o descartar cada
 *   sugerencia o todas; lo descartado no se envía; aviso fuera de fechas.
 * - TripFlightPicker (ficha): asociar un vuelo encontrado.
 * - Ficha del viaje: la demo no ve «Add existing flight» ni «Remove from trip».
 * - FlightForm: aviso, sin bloqueo, de una salida fuera del viaje (D3).
 * El servidor valida y autoriza (tests/Feature/TripFlightLinkingTest.php).
 */

const page = reactive({
    url: '/',
    props: {
        auth: { user: { is_demo: false } as { is_demo: boolean } },
        flash: { demoNotice: null as string | null },
        features: { uploads: false, photos: false },
        errors: {},
    },
});

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => page,
    Head: { render: () => null },
}));

const flight = (id: number, date: string, overrides: Partial<FlightRecord> = {}): FlightRecord => ({
    id,
    status: 'pending',
    confirmed: false,
    departure_date: date,
    flight_number: `TP${id}`,
    airline: null,
    departure: null,
    arrival: null,
    distance_km: 500,
    duration_minutes: null,
    trip: null,
    origin: { id: 1, code: 'MAD', airport: 'Barajas', city: 'Madrid', latitude: 40.4, longitude: -3.5, timezone: 'Europe/Madrid' },
    destination: { id: 2, code: 'LIS', airport: 'Humberto Delgado', city: 'Lisbon', latitude: 38.7, longitude: -9.1, timezone: 'Europe/Lisbon' },
    ...overrides,
});

let wrapper: VueWrapper | null = null;
let fetchMock: ReturnType<typeof vi.fn>;

const respondWith = (flights: FlightRecord[]) => {
    fetchMock.mockResolvedValue({ ok: true, json: async () => ({ flights }) });
};

beforeEach(() => {
    vi.useFakeTimers();
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    page.props.auth.user = { is_demo: false };
});

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

const settle = async () => {
    await vi.runAllTimersAsync();
    await flushPromises();
};

describe('TripFlightSuggestions', () => {
    const mountSuggestions = async (flights: FlightRecord[]) => {
        respondWith(flights);
        const { default: TripFlightSuggestions } = await import('../../resources/js/components/trips/TripFlightSuggestions.vue');
        let accepted: number[] = [];

        wrapper = mount(TripFlightSuggestions, {
            props: {
                startDate: '2026-05-10',
                endDate: '2026-05-20',
                modelValue: accepted,
                'onUpdate:modelValue': (value: number[]) => {
                    accepted = value;
                    wrapper!.setProps({ modelValue: value });
                },
            },
        });
        await settle();

        return { accepted: () => accepted };
    };

    const buttonIn = (id: number, label: string) =>
        wrapper!.get(`[data-suggestion="${id}"]`).findAll('button').find((button) => button.text() === label)!;

    const button = (label: string) => wrapper!.findAll('button').find((b) => b.text() === label);

    it('asks the server for the trip dates', async () => {
        await mountSuggestions([flight(1, '2026-05-12')]);

        expect(fetchMock.mock.calls[0][0]).toBe('/flights/suggestions?start_date=2026-05-10&end_date=2026-05-20');
    });

    it('accepts and dismisses suggestions one by one; dismissed ones are not sent', async () => {
        const state = await mountSuggestions([flight(1, '2026-05-12'), flight(2, '2026-05-14'), flight(3, '2026-05-16')]);

        await buttonIn(1, 'Accept').trigger('click');
        await buttonIn(2, 'Dismiss').trigger('click');

        expect(state.accepted()).toEqual([1]);
        expect(wrapper!.find('[data-suggestion="2"]').exists()).toBe(false);
        expect(wrapper!.get('[data-suggestion="1"]').text()).toContain('Will be added');

        await buttonIn(1, 'Undo').trigger('click');
        expect(state.accepted()).toEqual([]);
    });

    it('accepts or dismisses all of them at once', async () => {
        const state = await mountSuggestions([flight(1, '2026-05-12'), flight(2, '2026-05-14')]);

        await button('Accept all')!.trigger('click');
        expect(state.accepted()).toEqual([1, 2]);

        await buttonIn(1, 'Undo').trigger('click');
        await buttonIn(2, 'Undo').trigger('click');
        await button('Dismiss all')!.trigger('click');

        expect(state.accepted()).toEqual([]);
        expect(wrapper!.find('[data-flight-suggestions]').exists()).toBe(false);
    });

    it('warns about a flight in the one-day margin without blocking it', async () => {
        const state = await mountSuggestions([flight(1, '2026-05-09'), flight(2, '2026-05-12')]);

        expect(wrapper!.get('[data-suggestion="1"]').find('[data-outside-warning]').exists()).toBe(true);
        expect(wrapper!.get('[data-suggestion="2"]').find('[data-outside-warning]').exists()).toBe(false);

        await buttonIn(1, 'Accept').trigger('click');
        expect(state.accepted()).toEqual([1]);
    });

    it('does not ask the server without a valid date range', async () => {
        const { default: TripFlightSuggestions } = await import('../../resources/js/components/trips/TripFlightSuggestions.vue');
        wrapper = mount(TripFlightSuggestions, { props: { startDate: '2026-05-20', endDate: '2026-05-10' } });
        await settle();

        expect(fetchMock).not.toHaveBeenCalled();
    });
});

describe('TripFlightPicker', () => {
    it('shows the suggestions and attaches the chosen flight to the trip', async () => {
        respondWith([flight(7, '2026-05-21')]);
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const { default: TripFlightPicker } = await import('../../resources/js/components/trips/TripFlightPicker.vue');

        wrapper = mount(TripFlightPicker, { props: { tripId: 5, startDate: '2026-05-10', endDate: '2026-05-20' } });
        await settle();

        expect(fetchMock.mock.calls[0][0]).toBe('/flights/suggestions?start_date=2026-05-10&end_date=2026-05-20');
        expect(wrapper.get('[data-picker-flight="7"]').find('[data-outside-warning]').exists()).toBe(true);

        await wrapper.get('[data-picker-flight="7"] button').trigger('click');

        expect(post.mock.calls[0][0]).toBe('/trips/5/flights/attach');
        expect(post.mock.calls[0][1]).toEqual({ flight_ids: [7] });
    });

    it('searches by route and date', async () => {
        respondWith([]);
        const { default: TripFlightPicker } = await import('../../resources/js/components/trips/TripFlightPicker.vue');

        wrapper = mount(TripFlightPicker, { props: { tripId: 5, startDate: '2026-05-10', endDate: '2026-05-20' } });
        await settle();
        await wrapper.get('#flight-picker-query').setValue('MAD LIS');
        await wrapper.get('#flight-picker-date').setValue('2026-03-01');
        await settle();

        expect(fetchMock.mock.calls.at(-1)![0]).toBe('/flights/standalone?q=MAD+LIS&date=2026-03-01');
    });
});

describe('Trip page flights', () => {
    const mountShow = async () => {
        const { default: Show } = await import('../../resources/js/pages/Trips/Show.vue');

        return mount(Show, {
            props: {
                trip: { id: 5, name: 'Lisbon', description: null, start_date: '2026-05-10', end_date: '2026-05-20', status: 'completed', phase: 'completed', can_complete: false },
                visits: [],
                flights: [{ ...flight(1, '2026-05-09'), outside_trip_dates: true }, { ...flight(2, '2026-05-12'), outside_trip_dates: false }],
                photos: [],
                processing_photos_count: 0,
                google_map_lists: [],
                available_google_map_lists: [],
            },
            global: {
                stubs: {
                    AppLayout: { template: '<div><slot /></div>' },
                    TripVisitedMap: true,
                    TripFlightMap: true,
                    FlightForm: true,
                    TripFlightPicker: true,
                    Link: { template: '<a><slot /></a>' },
                },
            },
        });
    };

    it('lets the owner add existing flights and remove flights from the trip', async () => {
        wrapper = await mountShow();

        expect(wrapper.find('[data-add-existing-flight]').exists()).toBe(true);
        expect(wrapper.findAll('[data-detach-flight]')).toHaveLength(2);
        expect(wrapper.findAll('[data-outside-trip-dates]')).toHaveLength(1);

        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        vi.stubGlobal('confirm', () => true);
        await wrapper.findAll('[data-detach-flight]')[0].trigger('click');

        expect(post.mock.calls[0][0]).toBe('/trips/5/flights/1/detach');
    });

    it('hides adding, removing and creating flights from the demo account', async () => {
        page.props.auth.user = { is_demo: true };
        wrapper = await mountShow();

        expect(wrapper.find('[data-add-existing-flight]').exists()).toBe(false);
        expect(wrapper.find('[data-detach-flight]').exists()).toBe(false);
        expect(wrapper.findAll('button').some((button) => button.text().includes('Add flight'))).toBe(false);
        // El aviso es información: también lo ve la demo.
        expect(wrapper.findAll('[data-outside-trip-dates]')).toHaveLength(1);
    });
});

describe('FlightForm trip dates', () => {
    it('warns, without blocking, when the departure is outside the trip', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const { default: FlightForm } = await import('../../resources/js/components/flights/FlightForm.vue');

        wrapper = mount(FlightForm, { props: { tripId: 5, tripDates: { start_date: '2026-05-10', end_date: '2026-05-20' }, defaultDate: '2026-05-10' } });
        expect(wrapper.find('[data-outside-warning]').exists()).toBe(false);

        await wrapper.get('#flight-date').setValue('2026-05-09');
        expect(wrapper.find('[data-outside-warning]').exists()).toBe(true);

        await wrapper.get('form').trigger('submit');
        expect(post).toHaveBeenCalledTimes(1);
    });

    it('uses the dates of the trip chosen in Flight History', async () => {
        const { default: FlightForm } = await import('../../resources/js/components/flights/FlightForm.vue');

        wrapper = mount(FlightForm, { props: { trips: [{ id: 3, name: 'Lisbon', start_date: '2026-05-10', end_date: '2026-05-20' }] } });
        await wrapper.get('#flight-date').setValue('2026-06-01');
        expect(wrapper.find('[data-outside-warning]').exists()).toBe(false);

        await wrapper.get('#flight-trip').setValue('3');
        expect(wrapper.find('[data-outside-warning]').exists()).toBe(true);
    });
});
