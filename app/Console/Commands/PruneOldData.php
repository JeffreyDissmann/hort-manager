<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Absence;
use App\Models\DailyDeparture;
use App\Models\DailyProgram;
use App\Models\Excursion;
use Illuminate\Console\Command;

class PruneOldData extends Command
{
    protected $signature = 'hort:prune-old-data';

    protected $description = 'Delete day boards, day programs, excursions and absences older than the retention period';

    public function handle(): int
    {
        $weeks = (int) config('hort.retention_weeks');
        $cutoff = now()->subWeeks($weeks)->startOfDay()->toDateString();

        // Mass deletes (query builder) skip model events on purpose: pruning
        // weeks-old data must NOT fire the excursion "Ausflug abgesagt" Slack DMs.
        // The database foreign keys still cascade child_excursion / excursion_slack_messages.
        $departures = DailyDeparture::where('date', '<', $cutoff)->delete();
        $programs = DailyProgram::where('date', '<', $cutoff)->delete();
        $excursions = Excursion::where('date', '<', $cutoff)->delete();
        $absences = Absence::where('date', '<', $cutoff)->delete();

        $this->info("Älter als {$weeks} Wochen aufgeräumt: {$departures} Abholungen, {$programs} Programme, {$excursions} Ausflüge, {$absences} Abwesenheiten.");

        return self::SUCCESS;
    }
}
