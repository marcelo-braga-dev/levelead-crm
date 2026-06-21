<?php

namespace App\Console\Commands;

use App\Jobs\ArchiveStaleLeadsJob;
use Illuminate\Console\Command;

class ArchiveStaleLeadsCommand extends Command
{
    protected $signature = 'leads:archive-stale';

    protected $description = 'Arquiva leads não-terminais sem interação há 12+ meses.';

    public function handle(): int
    {
        $archived = ArchiveStaleLeadsJob::dispatchSync();

        $this->info("{$archived} lead(s) arquivado(s).");

        return self::SUCCESS;
    }
}
