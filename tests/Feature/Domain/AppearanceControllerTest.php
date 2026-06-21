<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\AppearanceController;
use App\Models\Setting;
use App\Models\User;

function validStageColorsPayload(): array
{
    return [
        'new' => '#111111',
        'attempting_contact' => '#222222',
        'contact_made' => '#333333',
        'qualified' => '#444444',
        'proposal_sent' => '#555555',
        'negotiation' => '#666666',
        'won' => '#777777',
        'lost' => '#888888',
    ];
}

it('lets an admin view the appearance page with current and default colors', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->get(route('admin.appearance.edit'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('current.primary_color', '#4F46E5')
        ->where('current.secondary_color', '#06B6D4')
        ->where('current.stage_colors', AppearanceController::DEFAULT_STAGE_COLORS)
        ->where('defaults.primary_color', '#4F46E5')
        ->where('defaults.stage_colors', AppearanceController::DEFAULT_STAGE_COLORS));
});

it('lets an admin update the primary, secondary and kanban stage colors', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->put(route('admin.appearance.update'), [
        'primary_color' => '#112233',
        'secondary_color' => '#445566',
        'stage_colors' => validStageColorsPayload(),
    ]);

    $response->assertRedirect();
    expect(Setting::get('theme.primary_color'))->toBe('#112233');
    expect(Setting::get('theme.secondary_color'))->toBe('#445566');
    expect(Setting::get('kanban.stage_colors'))->toBe(validStageColorsPayload());
});

it('merges partial stage color updates with the remaining defaults and ignores unknown stage keys', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->put(route('admin.appearance.update'), [
        'primary_color' => '#112233',
        'secondary_color' => '#445566',
        'stage_colors' => ['won' => '#00FF00', 'not_a_real_stage' => '#FFFFFF'],
    ]);

    $stageColors = Setting::get('kanban.stage_colors');
    expect($stageColors['won'])->toBe('#00FF00');
    expect($stageColors['lost'])->toBe(AppearanceController::DEFAULT_STAGE_COLORS['lost']);
    expect($stageColors)->not->toHaveKey('not_a_real_stage');
});

it('forbids manager and consultant from viewing or updating appearance', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);

    foreach ([$manager, $consultant] as $user) {
        $this->actingAs($user)->get(route('admin.appearance.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.appearance.update'), [
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'stage_colors' => validStageColorsPayload(),
        ])->assertForbidden();
    }
});

it('rejects invalid hex colors', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->put(route('admin.appearance.update'), [
        'primary_color' => 'red',
        'secondary_color' => '#fff',
        'stage_colors' => ['new' => 'not-a-hex'],
    ]);

    $response->assertSessionHasErrors(['primary_color', 'secondary_color', 'stage_colors.new']);
});

it('shares the current theme colors on every Inertia request, including guest pages', function () {
    Setting::set('theme.primary_color', '#ABCDEF');
    Setting::set('theme.secondary_color', '#123456');
    Setting::set('kanban.stage_colors', ['won' => '#00AA00']);

    $response = $this->get(route('login'));

    $response->assertInertia(fn ($page) => $page
        ->where('theme.primary', '#ABCDEF')
        ->where('theme.secondary', '#123456')
        ->where('theme.stageColors.won', '#00AA00'));
});
