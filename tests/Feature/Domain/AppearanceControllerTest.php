<?php

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;

it('lets an admin view the appearance page with current and default colors', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->get(route('admin.appearance.edit'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('current.primary_color', '#4F46E5')
        ->where('current.secondary_color', '#06B6D4')
        ->where('defaults.primary_color', '#4F46E5'));
});

it('lets an admin update the primary and secondary colors', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->put(route('admin.appearance.update'), [
        'primary_color' => '#112233',
        'secondary_color' => '#445566',
    ]);

    $response->assertRedirect();
    expect(Setting::get('theme.primary_color'))->toBe('#112233');
    expect(Setting::get('theme.secondary_color'))->toBe('#445566');
});

it('forbids manager and consultant from viewing or updating appearance', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);

    foreach ([$manager, $consultant] as $user) {
        $this->actingAs($user)->get(route('admin.appearance.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.appearance.update'), [
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
        ])->assertForbidden();
    }
});

it('rejects invalid hex colors', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->put(route('admin.appearance.update'), [
        'primary_color' => 'red',
        'secondary_color' => '#fff',
    ]);

    $response->assertSessionHasErrors(['primary_color', 'secondary_color']);
});

it('shares the current theme colors on every Inertia request, including guest pages', function () {
    Setting::set('theme.primary_color', '#ABCDEF');
    Setting::set('theme.secondary_color', '#123456');

    $response = $this->get(route('login'));

    $response->assertInertia(fn ($page) => $page
        ->where('theme.primary', '#ABCDEF')
        ->where('theme.secondary', '#123456'));
});
