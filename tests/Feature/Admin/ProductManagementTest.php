<?php

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;

it('lets admin and manager create, update and remove products', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager)->get(route('admin.products.index'))->assertOk();

    $this->actingAs($manager)->post(route('admin.products.store'), ['name' => 'Plano Essencial'])->assertRedirect();
    $product = Product::where('name', 'Plano Essencial')->first();
    expect($product)->not->toBeNull();

    $this->actingAs($manager)->patch(route('admin.products.update', $product), ['name' => 'Plano Essencial Plus'])->assertRedirect();
    expect($product->refresh()->name)->toBe('Plano Essencial Plus');

    $this->actingAs($manager)->delete(route('admin.products.destroy', $product))->assertRedirect();
    expect(Product::find($product->id))->toBeNull();
});

it('forbids consultant from accessing product management', function () {
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);

    $this->actingAs($consultant)->post(route('admin.products.store'), ['name' => 'X'])->assertForbidden();
});

it('removing a product preserves historical references via soft delete', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $product = Product::create(['name' => 'Plano Histórico']);

    $this->actingAs($admin)->delete(route('admin.products.destroy', $product))->assertRedirect();

    expect(Product::find($product->id))->toBeNull();
    expect($product->fresh()->deleted_at)->not->toBeNull();
});
