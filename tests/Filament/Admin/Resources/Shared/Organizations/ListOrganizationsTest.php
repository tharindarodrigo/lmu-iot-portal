<?php

use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\Shared\Organizations\Pages\ListOrganizations;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);
});

it('can render the organizations list page', function (): void {
    livewire(ListOrganizations::class)
        ->assertSuccessful();
});

it('can see organizations in the table', function (): void {
    $organizations = Organization::factory(3)->create();

    livewire(ListOrganizations::class)
        ->assertCanSeeTableRecords($organizations);
});

it('can search for organizations by name', function (): void {
    $org1 = Organization::factory()->create(['name' => 'Acme Corporation']);
    $org2 = Organization::factory()->create(['name' => 'Tech Solutions']);

    livewire(ListOrganizations::class)
        ->searchTable($org1->name)
        ->assertCanSeeTableRecords([$org1])
        ->assertCanNotSeeTableRecords([$org2]);
});

it('displays no records message when empty', function (): void {
    livewire(ListOrganizations::class)
        ->assertCountTableRecords(0);
});

it('can paginate organizations', function (): void {
    Organization::factory(10)->create();

    livewire(ListOrganizations::class)
        ->assertCountTableRecords(10);
});
