import { describe, expect, it } from 'vitest';
import { touchesYear } from '../../resources/js/utils/tripDates';

// Misma regla que App\Services\TravelStatistics: un viaje o una visita está en
// cada año natural que toca. Los filtros de las páginas dependen de ella.
describe('touchesYear', () => {
    it('includes every calendar year an interval touches', () => {
        expect(touchesYear('2025-12-30', '2026-01-02', 2025)).toBe(true);
        expect(touchesYear('2025-12-30', '2026-01-02', 2026)).toBe(true);
        expect(touchesYear('2025-12-30', '2026-01-02', 2024)).toBe(false);
        expect(touchesYear('2025-12-30', '2026-01-02', 2027)).toBe(false);
    });

    it('handles single-day and open-ended dates', () => {
        expect(touchesYear('2026-05-01', '2026-05-01', 2026)).toBe(true);
        expect(touchesYear('2026-05-01', null, 2026)).toBe(true);
        expect(touchesYear('2026-05-01', null, 2027)).toBe(false);
    });

    it('never places an undated interval in a year', () => {
        expect(touchesYear(null, null, 2026)).toBe(false);
        expect(touchesYear(undefined, '2026-05-01', 2026)).toBe(false);
    });
});
