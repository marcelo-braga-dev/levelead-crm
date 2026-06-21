<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateLeadScoresJob;
use Illuminate\Console\Command;

class RecalculateLeadScoresCommand extends Command
{
    protected $signature = 'leads:recalculate-scores';

    protected $description = 'Recalcula fit/intent/total score de todos os leads não-arquivados.';

    public function handle(): int
    {
        $recalculated = RecalculateLeadScoresJob::dispatchSync();

        $this->info("{$recalculated} lead(s) recalculado(s).");

        return self::SUCCESS;
    }
}
