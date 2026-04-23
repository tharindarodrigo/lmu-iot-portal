<?php

declare(strict_types=1);

use App\Domain\Shared\Models\Entity;
use App\Domain\Shared\Models\Organization;
use Database\Seeders\ImportLegacyEntitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::ensureDirectoryExists(database_path('seeders/exports'));
    File::delete(database_path('seeders/exports/teejay_entities.csv'));
});

afterEach(function (): void {
    File::delete(database_path('seeders/exports/teejay_entities.csv'));
});

it('imports only teejay entities and maps them by organization name', function (): void {
    $teejay = Organization::factory()->create([
        'name' => 'Teejay',
        'slug' => 'teejay',
    ]);

    Organization::factory()->create([
        'name' => 'WITCO',
        'slug' => 'witco',
    ]);

    Organization::factory()->create([
        'name' => 'SriLankan Airlines Limited',
        'slug' => 'srilankan-airlines-limited',
    ]);

    $csv = <<<'CSV'
legacy_id,name,parent_id,organization_id,organization_name,icon,uuid
1,Factory,,6,Teejay,factory,11111111-1111-1111-1111-111111111111
2,Line 1,1,6,Teejay,screenshot_region,22222222-2222-2222-2222-222222222222
3,Witco Root,,8,WITCO,location_city,33333333-3333-3333-3333-333333333333
4,Cargo,,5,SriLankan Airlines Limited,apartment,44444444-4444-4444-4444-444444444444
CSV;

    File::put(database_path('seeders/exports/teejay_entities.csv'), $csv);

    $this->seed(ImportLegacyEntitiesSeeder::class);

    $factory = Entity::query()->where('uuid', '11111111-1111-1111-1111-111111111111')->first();
    $line = Entity::query()->where('uuid', '22222222-2222-2222-2222-222222222222')->first();

    expect(Entity::query()->count())->toBe(2)
        ->and($factory)->not->toBeNull()
        ->and($line)->not->toBeNull()
        ->and($factory?->organization_id)->toBe($teejay->id)
        ->and($line?->organization_id)->toBe($teejay->id)
        ->and($line?->parent_id)->toBe($factory?->id)
        ->and($line?->label)->toBe('Factory -> Line 1')
        ->and(Entity::query()->where('uuid', '33333333-3333-3333-3333-333333333333')->exists())->toBeFalse()
        ->and(Entity::query()->where('uuid', '44444444-4444-4444-4444-444444444444')->exists())->toBeFalse();
});
