<?php

use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\Shared\Users\Pages\EditUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->admin);
});

it('can render the edit user page', function (): void {
    $user = User::factory()->create();

    livewire(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertSuccessful();
});

it('can update a user', function (): void {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    livewire(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);
});

it('populates form with existing data', function (): void {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    livewire(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertFormSet([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
});

it('validates required fields', function (): void {
    $user = User::factory()->create();

    livewire(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'name' => '',
            'email' => '',
        ])
        ->call('save')
        ->assertHasFormErrors([
            'name' => 'required',
            'email' => 'required',
        ]);
});

it('validates email format on update', function (): void {
    $user = User::factory()->create();

    livewire(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'email' => 'invalid-email',
        ])
        ->call('save')
        ->assertHasFormErrors(['email']);
});
