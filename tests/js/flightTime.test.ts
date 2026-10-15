import { describe, expect, it } from 'vitest';
import { normalizeTimeInput } from '../../resources/js/utils/flightTime';

// Horas escritas a mano en el formulario de vuelos (formato de 24 horas).
describe('normalizeTimeInput', () => {
    it.each([
        ['00:05', '00:05'],
        ['05:30', '05:30'],
        ['07:30', '07:30'],
        ['12:30', '12:30'],
        ['23:45', '23:45'],
        ['7:30', '07:30'],
        ['0:05', '00:05'],
        ['0730', '07:30'],
        ['2359', '23:59'],
        ['07.30', '07:30'],
        ['  12:30  ', '12:30'],
    ])('%j → %s', (typed, expected) => {
        expect(normalizeTimeInput(typed)).toBe(expected);
    });

    it.each(['', '   ', null, undefined])('%j is an empty (valid) time', (typed) => {
        expect(normalizeTimeInput(typed)).toBe('');
    });

    it.each(['7', '07', '07:', '7:3', '730', '24:00', '12:60', '99:99', 'ab:cd', '12:30:00', '-1:30', '12 30', '12:300'])('%j is rejected', (typed) => {
        expect(normalizeTimeInput(typed)).toBeNull();
    });
});
