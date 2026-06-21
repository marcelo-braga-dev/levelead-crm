<?php

use App\Enums\LeadStage;
use App\Jobs\ArchiveStaleLeadsJob;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

function createLeadForArchiveTest(array $attributes = []): Lead
{
    $company = Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa Archive Test',
    ]);

    return Lead::create([
        'company_id' => $company->id,
        'stage' => LeadStage::New->value,
        ...$attributes,
    ]);
}

it('archives non-terminal leads with no interaction in 12+ months', function () {
    $stale = createLeadForArchiveTest();
    $stale->forceFill(['created_at' => now()->subMonths(13)])->save();

    $staleByOldInteraction = createLeadForArchiveTest(['last_interaction_at' => now()->subMonths(15)]);

    $recent = createLeadForArchiveTest(['last_interaction_at' => now()->subMonths(1)]);

    $wonStale = createLeadForArchiveTest(['stage' => LeadStage::Won->value]);
    $wonStale->forceFill(['created_at' => now()->subMonths(20)])->save();

    $archived = ArchiveStaleLeadsJob::dispatchSync();

    expect($archived)->toBe(2);
    expect($stale->fresh()->archived_at)->not->toBeNull();
    expect($staleByOldInteraction->fresh()->archived_at)->not->toBeNull();
    expect($recent->fresh()->archived_at)->toBeNull();
    expect($wonStale->fresh()->archived_at)->toBeNull();
});

it('respects a lower leads.archive_stale_months setting than the default', function () {
    Setting::set('leads.archive_stale_months', 1);

    $stale = createLeadForArchiveTest();
    $stale->forceFill(['created_at' => now()->subMonths(2)])->save();

    $recent = createLeadForArchiveTest();
    $recent->forceFill(['created_at' => now()->subDays(10)])->save();

    $archived = ArchiveStaleLeadsJob::dispatchSync();

    expect($archived)->toBe(1);
    expect($stale->fresh()->archived_at)->not->toBeNull();
    expect($recent->fresh()->archived_at)->toBeNull();
});

it('does not re-archive an already archived lead', function () {
    $alreadyArchived = createLeadForArchiveTest(['archived_at' => now()->subDay()]);
    $alreadyArchived->forceFill(['created_at' => now()->subMonths(20)])->save();

    $archived = ArchiveStaleLeadsJob::dispatchSync();

    expect($archived)->toBe(0);
});

it('archives every eligible lead across multiple chunks, none skipped', function () {
    // O job arquiva 100 por vez (chunkById) e o próprio callback grava `archived_at`, a coluna
    // usada no WHERE. Com chunk() por OFFSET isso pularia registros a partir do 2º lote; este
    // teste prova que chunkById() não tem esse problema, criando mais leads que o tamanho do lote.
    $total = 105;

    for ($i = 0; $i < $total; $i++) {
        $lead = createLeadForArchiveTest();
        $lead->forceFill(['created_at' => now()->subMonths(13)])->save();
    }

    $archived = ArchiveStaleLeadsJob::dispatchSync();

    expect($archived)->toBe($total);
    expect(Lead::whereNull('archived_at')->count())->toBe(0);
});

it('logs and continues past a lead that fails to archive instead of aborting the rest', function () {
    Log::spy();

    $broken = createLeadForArchiveTest();
    $broken->forceFill(['created_at' => now()->subMonths(13)])->save();

    $healthy = createLeadForArchiveTest();
    $healthy->forceFill(['created_at' => now()->subMonths(13)])->save();

    Lead::updating(function (Lead $lead) use ($broken) {
        if ($lead->is($broken)) {
            throw new RuntimeException('falha forçada para teste');
        }
    });

    $archived = ArchiveStaleLeadsJob::dispatchSync();

    expect($archived)->toBe(1);
    expect($broken->fresh()->archived_at)->toBeNull();
    expect($healthy->fresh()->archived_at)->not->toBeNull();
    Log::shouldHaveReceived('error')->once();
});
