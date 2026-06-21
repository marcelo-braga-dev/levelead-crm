<?php

use App\Enums\UserRole;
use App\Models\LeadDistributionRule;
use App\Models\LeadScoringRule;
use App\Models\User;

it('lets an admin manage distribution rules', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('admin.rules.index'))->assertOk();

    $this->actingAs($admin)->post(route('admin.distribution-rules.store'), [
        'name' => 'Round robin geral',
        'strategy' => 'round_robin',
        'team_id' => null,
        'state_id' => null,
        'product_id' => null,
        'priority' => 10,
        'is_active' => true,
    ])->assertRedirect();

    $rule = LeadDistributionRule::where('name', 'Round robin geral')->first();
    expect($rule)->not->toBeNull();

    $this->actingAs($admin)->patch(route('admin.distribution-rules.update', $rule), [
        'name' => $rule->name,
        'strategy' => $rule->strategy,
        'team_id' => null,
        'state_id' => null,
        'product_id' => null,
        'priority' => $rule->priority,
        'is_active' => false,
    ])->assertRedirect();

    expect($rule->refresh()->is_active)->toBeFalse();
});

it('lets an admin manage scoring rules', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post(route('admin.scoring-rules.store'), [
        'criterion' => 'company_size',
        'criterion_value' => 'Grande',
        'score_weight' => 30,
        'is_active' => true,
    ])->assertRedirect();

    $rule = LeadScoringRule::where('criterion', 'company_size')->where('criterion_value', 'Grande')->first();
    expect($rule)->not->toBeNull();

    $this->actingAs($admin)->patch(route('admin.scoring-rules.update', $rule), [
        'criterion' => $rule->criterion,
        'criterion_value' => $rule->criterion_value,
        'score_weight' => 50,
        'is_active' => true,
    ])->assertRedirect();

    expect($rule->refresh()->score_weight)->toBe(50);
});

it('forbids manager and consultant from accessing rule management', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);

    foreach ([$manager, $consultant] as $user) {
        $this->actingAs($user)->get(route('admin.rules.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.scoring-rules.store'), [
            'criterion' => 'x', 'criterion_value' => 'y', 'score_weight' => 1, 'is_active' => true,
        ])->assertForbidden();
    }
});
