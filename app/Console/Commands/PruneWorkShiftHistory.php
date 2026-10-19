<?php

namespace App\Console\Commands;

use App\Models\WorkShift;
use Illuminate\Console\Command;

class PruneWorkShiftHistory extends Command
{
    protected $signature = 'work-shifts:prune';

    protected $description = 'Delete closed work shifts older than 30 days';

    public function handle(): int
    {
        $deleted = WorkShift::query()
            ->whereNotNull('closed_at')
            ->where('closed_at', '<', now()->subDays(30))
            ->delete();

        $this->info("Deleted {$deleted} work shift record(s) older than 30 days.");

        return self::SUCCESS;
    }
}
