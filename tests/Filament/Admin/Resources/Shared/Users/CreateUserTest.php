<?php

use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\Shared\Users\Pages\CreateUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->admin);
});

it('can render the create user page', function (): void {
    livewire(CreateUser::class)
        ->assertSuccessful();
});

it('can create a new user', function (): void {
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
    ]);
});

it('validates required fields', function (): void {
    livewire(CreateUser::class)
        ->fillForm([
            'name' => '',
            'email' => '',
            'password' => '',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'email' => 'required',
            'password' => 'required',
        ]);
});

it('validates email format', function (): void {
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'Password123!',
        ])
        ->call('create')
        ->assertHasFormErrors(['email']);
});

it('validates unique email', function (): void {
    $existingUser = User::factory()->create();

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'New User',
            'email' => $existingUser->email,
            'password' => 'Password123!',
        ])
        ->call('create')
        ->assertHasFormErrors(['email']);
});

it('validates password confirmation', function (): void {
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'DifferentPassword123!',
        ])
        ->call('create')
        ->assertHasFormErrors(['password_confirmation']);
});
