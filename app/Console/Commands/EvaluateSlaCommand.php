<?php

namespace App\Console\Commands;

use App\Jobs\EvaluateLeadSlaJob;
use Illuminate\Console\Command;

class EvaluateSlaCommand extends Command
{
    protected $signature = 'leads:evaluate-sla';

    protected $description = 'Avalia condições de SLA de todos os leads ativos e gera/resolve alertas.';

    public function handle(): int
    {
        $raised = EvaluateLeadSlaJob::dispatchSync();

        $this->info("{$raised} alerta(s) de SLA gerado(s).");

        return self::SUCCESS;
    }
}
