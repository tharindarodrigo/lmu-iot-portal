<?php

declare(strict_types=1);

use App\Domain\Shared\Models\Entity;
use App\Domain\Shared\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates chained label from root to child', function (): void {
    $org = Organization::factory()->create();

    $root = Entity::factory()->forOrganization($org->id)->create(['name' => 'Building A']);
    $floor = Entity::factory()->forOrganization($org->id)->withParent($root->id)->create(['name' => 'Floor 4']);
    $zone = Entity::factory()->forOrganization($org->id)->withParent($floor->id)->create(['name' => 'Zone 3']);

    expect($root->fresh()->label)->toBe('Building A');
    expect($floor->fresh()->label)->toBe('Building A -> Floor 4');
    expect($zone->fresh()->label)->toBe('Building A -> Floor 4 -> Zone 3');
});

it('updates label when name or parent changes', function (): void {
    $org = Organization::factory()->create();

    $a = Entity::factory()->forOrganization($org->id)->create(['name' => 'A']);
    $b = Entity::factory()->forOrganization($org->id)->withParent($a->id)->create(['name' => 'B']);

    expect($b->fresh()->label)->toBe('A -> B');

    $a->update(['name' => 'AA']);
    expect($b->fresh()->label)->toBe('AA -> B');
});
