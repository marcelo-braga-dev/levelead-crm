<?php

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;

it('lets an admin create, update and remove teams', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('admin.teams.index'))->assertOk();

    $this->actingAs($admin)->post(route('admin.teams.store'), ['name' => 'Equipe Norte'])->assertRedirect();
    $team = Team::where('name', 'Equipe Norte')->first();
    expect($team)->not->toBeNull();

    $this->actingAs($admin)->patch(route('admin.teams.update', $team), ['name' => 'Equipe Norte Renomeada'])->assertRedirect();
    expect($team->refresh()->name)->toBe('Equipe Norte Renomeada');

    $this->actingAs($admin)->delete(route('admin.teams.destroy', $team))->assertRedirect();
    expect(Team::find($team->id))->toBeNull();
});

it('forbids manager and consultant from accessing team management', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);

    foreach ([$manager, $consultant] as $user) {
        $this->actingAs($user)->get(route('admin.teams.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.teams.store'), ['name' => 'X'])->assertForbidden();
    }
});

it('removing a team is a soft delete and never deletes its users', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $team = Team::create(['name' => 'Equipe Sul']);
    $consultant = User::factory()->create(['role' => UserRole::Consultant, 'team_id' => $team->id]);

    $this->actingAs($admin)->delete(route('admin.teams.destroy', $team))->assertRedirect();

    expect(Team::find($team->id))->toBeNull();
    expect($team->fresh()->deleted_at)->not->toBeNull();
    expect(User::find($consultant->id))->not->toBeNull();
});
