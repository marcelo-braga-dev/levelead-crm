<?php

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('lets an admin list, create, update and deactivate users', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $team = Team::create(['name' => 'Equipe A']);

    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Novo Consultor',
        'email' => 'novo@levelead.com.br',
        'password' => 'password123',
        'role' => 'consultant',
        'team_id' => $team->id,
    ])->assertRedirect();

    $created = User::where('email', 'novo@levelead.com.br')->first();
    expect($created)->not->toBeNull();
    expect($created->team_id)->toBe($team->id);

    $this->actingAs($admin)->patch(route('admin.users.update', $created), [
        'name' => 'Consultor Renomeado',
        'email' => 'novo@levelead.com.br',
        'password' => '',
        'role' => 'consultant',
        'team_id' => $team->id,
    ])->assertRedirect();

    expect($created->refresh()->name)->toBe('Consultor Renomeado');

    $this->actingAs($admin)->delete(route('admin.users.destroy', $created))->assertRedirect();

    expect(User::find($created->id))->toBeNull();
    expect($created->fresh()->deleted_at)->not->toBeNull();
});

it('forbids manager and consultant from accessing user management', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);

    foreach ([$manager, $consultant] as $user) {
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }
});

it('blocks an admin from deactivating their own account', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertForbidden();

    expect(User::find($admin->id))->not->toBeNull();
});

it('blocks login for a deactivated user', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $consultant = User::factory()->create(['role' => UserRole::Consultant, 'password' => bcrypt('password123')]);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $consultant))->assertRedirect();

    // `actingAs($admin)` continua valendo na sessão de teste — sem deslogar, a próxima
    // requisição a `/login` cairia no middleware `guest` (já autenticado) antes mesmo de
    // tentar autenticar como o consultor desativado.
    Auth::logout();

    $this->post('/login', ['email' => $consultant->email, 'password' => 'password123'])
        ->assertSessionHasErrors();
    $this->assertGuest();
});
