<?php

namespace App\Console\Commands;

use App\Jobs\SendSlaDigestJob;
use Illuminate\Console\Command;

class SendSlaDigestCommand extends Command
{
    protected $signature = 'leads:send-sla-digest';

    protected $description = 'Envia 1 e-mail diário a admin/manager resumindo todos os alertas de SLA em aberto.';

    public function handle(): int
    {
        $count = SendSlaDigestJob::dispatchSync();

        $this->info("Digest enviado com {$count} alerta(s) em aberto.");

        return self::SUCCESS;
    }
}
