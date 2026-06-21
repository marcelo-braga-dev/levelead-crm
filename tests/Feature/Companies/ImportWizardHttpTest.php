<?php

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\ImportProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('walks through upload, mapping, confirmation and processing via the real routes', function () {
    Storage::fake('local');

    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $csv = UploadedFile::fake()->createWithContent(
        'lote.csv',
        "Razão Social;cnpj da empresa\nEmpresa Wizard LTDA;11333555000199\n",
    );

    $uploadResponse = $this->actingAs($manager)->post(route('companies.import.store'), ['file' => $csv]);

    $batch = ImportBatch::first();
    expect($batch)->not->toBeNull();
    $uploadResponse->assertRedirect(route('companies.import.mapping', $batch));

    $mappingResponse = $this->actingAs($manager)->get(route('companies.import.mapping', $batch));
    $mappingResponse->assertOk();
    $mappingResponse->assertInertia(fn ($page) => $page
        ->where('suggestedMapping.Razão Social', 'razao_social')
        ->where('suggestedMapping.cnpj da empresa', 'cnpj'));

    $confirmMappingResponse = $this->actingAs($manager)->post(route('companies.import.confirmMapping', $batch), [
        'mapping' => ['Razão Social' => 'razao_social', 'cnpj da empresa' => 'cnpj'],
        'save_as_profile' => true,
        'profile_name' => 'Perfil HTTP',
    ]);
    $confirmMappingResponse->assertRedirect(route('companies.import.confirm', $batch));
    expect(ImportProfile::where('name', 'Perfil HTTP')->exists())->toBeTrue();

    $confirmResponse = $this->actingAs($manager)->get(route('companies.import.confirm', $batch));
    $confirmResponse->assertOk();
    $confirmResponse->assertInertia(fn ($page) => $page->where('batch.status', 'pending'));

    $processResponse = $this->actingAs($manager)->post(route('companies.import.process', $batch));
    $processResponse->assertRedirect(route('companies.import.confirm', $batch));

    expect($batch->fresh()->status)->toBe('completed');
    expect(Company::where('cnpj', '11333555000199')->exists())->toBeTrue();

    $finalConfirmResponse = $this->actingAs($manager)->get(route('companies.import.confirm', $batch));
    $finalConfirmResponse->assertInertia(fn ($page) => $page
        ->where('batch.status', 'completed')
        ->where('batch.created_rows', 1));
});

it('forbids a consultant from accessing the import wizard', function () {
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);

    $this->actingAs($consultant)->get(route('companies.import'))->assertForbidden();
});
