<?php

namespace App\Console\Commands;

use App\Jobs\DistributeUnassignedLeadsJob;
use Illuminate\Console\Command;

class RunLeadDistributionCommand extends Command
{
    protected $signature = 'leads:run-distribution';

    protected $description = 'Distribui leads sem consultor entre os consultores elegíveis (round-robin/regras).';

    public function handle(): int
    {
        $distributed = DistributeUnassignedLeadsJob::dispatchSync();

        $this->info("{$distributed} lead(s) distribuído(s).");

        return self::SUCCESS;
    }
}
