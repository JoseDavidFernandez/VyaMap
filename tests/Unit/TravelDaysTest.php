<?php

namespace Tests\Unit;

use App\Services\TravelStatistics;
use PHPUnit\Framework\TestCase;

/**
 * Días viajados (TravelStatistics::travelDays): días naturales con inicio y
 * fin incluidos, unión de intervalos y reparto por año natural. Sin base de
 * datos: solo fechas.
 */
class TravelDaysTest extends TestCase
{
    public function test_a_single_day_trip_is_one_day(): void
    {
        $this->assertSame(['total' => 1, 'by_year' => [2026 => 1], 'invalid' => 0], TravelStatistics::travelDays([['2026-05-01', '2026-05-01']]));
    }

    public function test_a_trip_across_new_year_splits_its_days_between_both_years(): void
    {
        // 30 y 31 de diciembre; 1 y 2 de enero.
        $this->assertSame(['total' => 4, 'by_year' => [2026 => 2, 2025 => 2], 'invalid' => 0], TravelStatistics::travelDays([['2025-12-30', '2026-01-02']]));
    }

    public function test_overlapping_trips_never_count_a_day_twice(): void
    {
        $days = TravelStatistics::travelDays([
            ['2026-05-01', '2026-05-10'],
            ['2026-05-05', '2026-05-12'], // solapa: 11 y 12 son nuevos
            ['2026-05-03', '2026-05-04'], // dentro del primero
        ]);

        $this->assertSame(12, $days['total']);
    }

    public function test_back_to_back_trips_are_contiguous_days_not_a_gap(): void
    {
        $days = TravelStatistics::travelDays([['2026-05-01', '2026-05-03'], ['2026-05-04', '2026-05-05'], ['2026-06-01', '2026-06-02']]);

        $this->assertSame(['total' => 7, 'by_year' => [2026 => 7], 'invalid' => 0], $days);
    }

    public function test_the_order_of_the_intervals_does_not_matter(): void
    {
        $intervals = [['2026-06-01', '2026-06-02'], ['2025-12-31', '2026-01-01'], ['2026-01-01', '2026-01-03']];

        $this->assertSame(TravelStatistics::travelDays($intervals), TravelStatistics::travelDays(array_reverse($intervals)));
        $this->assertSame(['total' => 6, 'by_year' => [2026 => 5, 2025 => 1], 'invalid' => 0], TravelStatistics::travelDays($intervals));
    }

    public function test_leap_days_are_counted(): void
    {
        $this->assertSame(3, TravelStatistics::travelDays([['2024-02-28', '2024-03-01']])['total']);
    }

    public function test_incomplete_or_reversed_intervals_are_left_out_and_reported(): void
    {
        $days = TravelStatistics::travelDays([['2026-05-10', '2026-05-01'], [null, '2026-01-01'], ['2026-01-01', null], ['not-a-date', '2026-01-01'], ['2026-02-30', '2026-03-01'], ['2026-03-01', '2026-03-02']]);

        $this->assertSame(['total' => 2, 'by_year' => [2026 => 2], 'invalid' => 5], $days);
    }

    public function test_no_trips_is_zero_days(): void
    {
        $this->assertSame(['total' => 0, 'by_year' => [], 'invalid' => 0], TravelStatistics::travelDays([]));
    }

    public function test_years_of_an_interval_include_every_year_it_touches(): void
    {
        $this->assertSame([2025, 2026, 2027], TravelStatistics::yearsOf('2025-12-30', '2027-01-01'));
        $this->assertSame([2026], TravelStatistics::yearsOf('2026-05-01', '2026-05-01'));
        $this->assertSame([], TravelStatistics::yearsOf('2026-05-02', '2026-05-01'));
        $this->assertSame([], TravelStatistics::yearsOf(null, null));
    }
}
