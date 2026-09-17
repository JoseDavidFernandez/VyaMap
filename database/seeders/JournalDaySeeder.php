<?php

namespace Database\Seeders;

use App\Models\Journal;
use App\Models\JournalDay;
use Illuminate\Database\Seeder;

class JournalDaySeeder extends Seeder
{
    public function run(): void
    {
        $journal = Journal::where('title', 'Madrid & Barcelona')
            ->firstOrFail();

        JournalDay::create([
            'journal_id' => $journal->id,
            'day_number' => 1,
            'date' => '2026-09-10',
            'title' => 'Llegada y visita de Madrid',
        ]);

        JournalDay::create([
            'journal_id' => $journal->id,
            'day_number' => 2,
            'date' => '2026-09-11',
            'title' => 'Barcelona',
        ]);

        JournalDay::create([
            'journal_id' => $journal->id,
            'day_number' => 3,
            'date' => '2026-09-12',
            'title' => 'Último día',
        ]);
    }
}