import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import FlightForm from '../../resources/js/components/flights/FlightForm.vue';
import type { FlightRecord } from '../../resources/js/types/flights';

/*
 * Formulario de vuelo (resources/js/components/flights/FlightForm.vue).
 *
 * Las horas son campos de texto en formato de 24 horas (HH:MM), no
 * <input type="time">: así se comportan igual en todos los navegadores y
 * también en happy-dom, de modo que estas pruebas escriben como un usuario.
 *
 * Regresiones cubiertas:
 * - Cambiar o borrar la salida nunca borra la llegada ni su día.
 * - Ningún render (de este u otro campo) deshace lo que se está tecleando.
 * - Sin avisos mientras se escribe; al salir del campo, la hora se normaliza
 *   o se avisa; una hora inválida bloquea los tres botones de guardar.
 * - Las horas vacías son válidas; al servidor solo llega HH:MM o null.
 */

const madrid = 'Europe/Madrid';
const warning = 'Use the 24-hour format HH:MM';

const flight = (overrides: Partial<FlightRecord> = {}): FlightRecord => ({
    id: 1,
    status: 'flown',
    confirmed: false,
    departure_date: '2026-03-10',
    flight_number: 'IB1',
    airline: 'Iberia',
    // 07:30 en Madrid; llegada 08:45 del día siguiente.
    departure: '2026-03-10T06:30:00Z',
    arrival: '2026-03-11T07:45:00Z',
    distance_km: 483,
    duration_minutes: 1515,
    trip: null,
    origin: { id: 10, code: 'MAD', airport: 'Barajas', city: 'Madrid', latitude: 40.47, longitude: -3.56, timezone: madrid },
    destination: { id: 20, code: 'BCN', airport: 'El Prat', city: 'Barcelona', latitude: 41.29, longitude: 2.07, timezone: madrid },
    ...overrides,
});

let wrapper: VueWrapper | null = null;
let post: ReturnType<typeof vi.spyOn>;
let put: ReturnType<typeof vi.spyOn>;

const mountForm = (props: Record<string, unknown> = {}) => {
    wrapper = mount(FlightForm, { props, attachTo: document.body });

    return wrapper;
};

const field = (id: string) => wrapper!.get<HTMLInputElement>(`#${id}`);
const departure = () => field('flight-departure-time');
const arrival = () => field('flight-arrival-time');
const day = () => wrapper!.get<HTMLSelectElement>('#flight-arrival-day');
const text = () => wrapper!.text();
const save = () => wrapper!.get('form').trigger('submit');
const saveButtons = () => wrapper!.findAll('button').filter((b) => /^Save/.test(b.text()));
const sentData = (spy: ReturnType<typeof vi.spyOn>, call = 0) => spy.mock.calls[call][1] as Record<string, unknown>;

// Escribe como un usuario: un carácter cada vez, con su evento input.
const typeInto = async (input: ReturnType<typeof field>, value: string, onEachStep?: (typed: string) => void) => {
    for (let i = 1; i <= value.length; i++) {
        await input.setValue(value.slice(0, i));
        onEachStep?.(value.slice(0, i));
    }
};

// Sustituye la hora: borra el campo, escribe y sale de él.
const retype = async (input: ReturnType<typeof field>, value: string) => {
    await input.setValue('');
    await typeInto(input, value);
    await input.trigger('blur');
};

beforeEach(() => {
    post = vi.spyOn(router, 'post').mockImplementation(() => {});
    put = vi.spyOn(router, 'put').mockImplementation(() => {});
});

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

describe('creating a flight', () => {
    it.each([
        ['Flight History', {}, '/flights'],
        ['trip page', { tripId: 5 }, '/trips/5/flights'],
    ])('sends departure and arrival from the %s', async (_page, props, url) => {
        mountForm(props);

        await field('flight-date').setValue('2026-03-10');
        await retype(departure(), '23:45');
        await retype(arrival(), '00:50');
        await day().setValue('1');
        await save();

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe(url);
        expect(sentData(post)).toMatchObject({ departure_date: '2026-03-10', departure_time: '23:45', arrival_time: '00:50', arrival_date: '2026-03-11' });
    });

    it('accepts empty optional times', async () => {
        mountForm();

        await field('flight-date').setValue('2019-04-12');
        await departure().trigger('blur');
        await arrival().trigger('blur');
        await save();

        expect(text()).not.toContain(warning);
        expect(sentData(post)).toMatchObject({ departure_time: null, arrival_time: null, arrival_date: null });
    });

    it.each(['00:05', '05:30', '07:30', '12:30', '23:45'])('accepts %s typed digit by digit', async (time) => {
        mountForm();

        await field('flight-date').setValue('2026-03-10');
        await retype(departure(), time);
        await save();

        expect(departure().element.value).toBe(time);
        expect(sentData(post)).toMatchObject({ departure_time: time });
    });
});

describe('editing a flight', () => {
    it('loads the local times and the arrival day', () => {
        mountForm({ flight: flight() });

        expect(departure().element.value).toBe('07:30');
        expect(arrival().element.value).toBe('08:45');
        expect(day().element.value).toBe('1');
        expect(text()).not.toContain(warning);
    });

    it.each([
        ['Flight History', {}, '/flights/1'],
        ['trip page', { tripId: 5 }, '/trips/5/flights/1'],
    ])('saves unchanged times exactly as loaded from the %s', async (_page, props, url) => {
        mountForm({ flight: flight(), ...props });
        await save();

        expect(put.mock.calls[0][0]).toBe(url);
        expect(sentData(put)).toMatchObject({ departure_date: '2026-03-10', departure_time: '07:30', arrival_time: '08:45', arrival_date: '2026-03-11' });

        if ('tripId' in props) {
            expect(sentData(put)).not.toHaveProperty('trip_id');
        }
    });

    it.each(['00:05', '05:30', '07:30', '12:30', '23:45'])('keeps the departure time %s unchanged when saved', async (time) => {
        const [hours, minutes] = time.split(':').map(Number);
        const departureAt = new Date(Date.UTC(2026, 2, 10, hours - 1, minutes)).toISOString(); // Madrid = UTC+1 en marzo

        mountForm({ flight: flight({ departure: departureAt, arrival: null }) });
        expect(departure().element.value).toBe(time);

        await save();

        expect(sentData(put)).toMatchObject({ departure_date: '2026-03-10', departure_time: time, arrival_time: null, arrival_date: null });
    });

    it('changing the departure several times keeps the arrival and its day', async () => {
        mountForm({ flight: flight() });

        for (const time of ['05:30', '12:30', '00:05']) {
            await retype(departure(), time);

            expect(arrival().element.value).toBe('08:45');
            expect(day().element.value).toBe('1');
        }

        await save();

        expect(sentData(put)).toMatchObject({ departure_time: '00:05', arrival_time: '08:45', arrival_date: '2026-03-11' });
    });

    it('can clear the departure and type it again without touching the arrival', async () => {
        mountForm({ flight: flight() });

        await departure().setValue('');
        await departure().trigger('blur');

        expect(arrival().element.value).toBe('08:45');
        expect(day().element.value).toBe('1');
        expect(text()).not.toContain(warning);

        await retype(departure(), '06:10');
        await save();

        expect(sentData(put)).toMatchObject({ departure_time: '06:10', arrival_time: '08:45', arrival_date: '2026-03-11' });
    });
});

describe('typing a time', () => {
    it('is never rewritten or warned about while typing', async () => {
        mountForm({ flight: flight() });
        await departure().setValue('');

        await typeInto(departure(), '05:30', (typed) => {
            expect(departure().element.value).toBe(typed);
            expect(text()).not.toContain(warning);
        });
    });

    it('a render caused by another field keeps a half-typed time', async () => {
        mountForm({ flight: flight() });

        await departure().setValue('');
        await typeInto(departure(), '05:');
        await retype(arrival(), '09:00');
        await field('flight-number').setValue('IB2');
        wrapper!.vm.$forceUpdate();
        await nextTick();

        expect(departure().element.value).toBe('05:');
    });

    it.each([
        ['7:30', '07:30'],
        ['0730', '07:30'],
        ['07.30', '07:30'],
        [' 12:30 ', '12:30'],
    ])('normalises %j to %s when leaving the field', async (typed, expected) => {
        mountForm();

        await departure().setValue(typed);
        await departure().trigger('blur');

        expect(departure().element.value).toBe(expected);
        expect(text()).not.toContain(warning);
    });
});

describe('invalid or incomplete times', () => {
    it.each(['7:3', '07:', '730', '24:00', '12:60', 'ab:cd'])('%j is rejected for every save button', async (typed) => {
        mountForm();

        await field('flight-date').setValue('2026-03-10');
        await departure().setValue(typed);
        await departure().trigger('blur');

        expect(text()).toContain(warning);
        expect(departure().element.value).toBe(typed);

        for (const button of saveButtons()) {
            await button.trigger('click');
        }

        expect(post).not.toHaveBeenCalled();
    });

    it('are caught when saving without leaving the field', async () => {
        mountForm({ flight: flight() });

        await arrival().setValue('8:4');
        await save();

        expect(put).not.toHaveBeenCalled();
        expect(text()).toContain(warning);
    });

    it('the warning goes away as soon as the time is corrected', async () => {
        mountForm();

        await departure().setValue('7:3');
        await departure().trigger('blur');
        expect(text()).toContain(warning);

        await departure().setValue('7:30');

        expect(text()).not.toContain(warning);
    });
});

describe('arrival fields', () => {
    it('are never disabled', () => {
        mountForm();

        expect(arrival().attributes('disabled')).toBeUndefined();
        expect(day().attributes('disabled')).toBeUndefined();
    });

    it('send an arrival without departure instead of dropping it, with a hint', async () => {
        mountForm();

        await field('flight-date').setValue('2026-03-10');
        await retype(arrival(), '10:00');

        expect(text()).toContain('an arrival time needs one');

        await save();

        expect(sentData(post)).toMatchObject({ departure_time: null, arrival_time: '10:00', arrival_date: '2026-03-10' });
    });

    it('"Save & add return" empties both times for the next flight', async () => {
        post.mockImplementation((...args: unknown[]) => (args[2] as { onSuccess?: () => void }).onSuccess?.());
        mountForm();

        await field('flight-date').setValue('2026-03-10');
        await retype(departure(), '07:30');
        await retype(arrival(), '08:45');
        await wrapper!.findAll('button').find((b) => b.text() === 'Save & add return')!.trigger('click');
        await nextTick();

        expect(departure().element.value).toBe('');
        expect(arrival().element.value).toBe('');
        expect(text()).toContain('Add the return flight');
    });
});
