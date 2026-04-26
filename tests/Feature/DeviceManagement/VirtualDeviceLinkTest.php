<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\VirtualDeviceLink;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('adds a virtual device flag and composition table for standard device foundations', function (): void {
    expect(Schema::hasColumn('devices', 'is_virtual'))->toBeTrue()
        ->and(Schema::hasColumn('virtual_device_links', 'virtual_device_id'))->toBeTrue()
        ->and(Schema::hasColumn('virtual_device_links', 'source_device_id'))->toBeTrue()
        ->and(Schema::hasColumn('virtual_device_links', 'purpose'))->toBeTrue()
        ->and(Schema::hasColumn('virtual_device_links', 'sequence'))->toBeTrue()
        ->and(Schema::hasColumn('virtual_device_links', 'metadata'))->toBeTrue();
});

it('supports purpose based links from one virtual device to many source devices', function (): void {
    $virtualDevice = Device::factory()->virtual()->create();
    $statusDevice = Device::factory()->create();
    $energyDevice = Device::factory()->create();

    VirtualDeviceLink::query()->create([
        'virtual_device_id' => $virtualDevice->id,
        'source_device_id' => $statusDevice->id,
        'purpose' => 'status',
        'sequence' => 1,
        'metadata' => ['legacy_label' => 'status'],
    ]);

    VirtualDeviceLink::query()->create([
        'virtual_device_id' => $virtualDevice->id,
        'source_device_id' => $energyDevice->id,
        'purpose' => 'energy',
        'sequence' => 1,
        'metadata' => ['legacy_label' => 'energy'],
    ]);

    $virtualDevice->load('virtualDeviceLinks.sourceDevice');
    $statusDevice->load('sourceDeviceLinks.virtualDevice');

    expect($virtualDevice->isVirtual())->toBeTrue()
        ->and(Device::query()->virtual()->pluck('id')->all())->toContain($virtualDevice->id)
        ->and(Device::query()->physical()->pluck('id')->all())->toContain($statusDevice->id)
        ->and($virtualDevice->virtualDeviceLinks)->toHaveCount(2)
        ->and($virtualDevice->virtualDeviceLinks->pluck('purpose')->all())->toBe(['energy', 'status'])
        ->and($virtualDevice->virtualDeviceLinks->firstWhere('purpose', 'status')?->sourceDevice?->is($statusDevice))->toBeTrue()
        ->and($statusDevice->sourceDeviceLinks->first()?->virtualDevice?->is($virtualDevice))->toBeTrue();
});

it('keeps virtual source links separate from the physical parent child hierarchy', function (): void {
    $physicalHub = Device::factory()->create();
    $physicalChild = Device::factory()->create([
        'parent_device_id' => $physicalHub->id,
    ]);
    $virtualDevice = Device::factory()->virtual()->create();

    $link = VirtualDeviceLink::query()->create([
        'virtual_device_id' => $virtualDevice->id,
        'source_device_id' => $physicalChild->id,
        'purpose' => 'length',
        'sequence' => 1,
    ]);

    $physicalChild->load('parentDevice', 'sourceDeviceLinks.virtualDevice');

    expect($physicalChild->isChildDevice())->toBeTrue()
        ->and($physicalChild->parentDevice?->is($physicalHub))->toBeTrue()
        ->and($physicalChild->sourceDeviceLinks)->toHaveCount(1)
        ->and($physicalChild->sourceDeviceLinks->first()?->is($link))->toBeTrue()
        ->and($physicalChild->sourceDeviceLinks->first()?->virtualDevice?->is($virtualDevice))->toBeTrue();
});

it('prevents duplicate purpose links for the same virtual and source device pair', function (): void {
    $virtualDevice = Device::factory()->virtual()->create();
    $sourceDevice = Device::factory()->create();

    VirtualDeviceLink::query()->create([
        'virtual_device_id' => $virtualDevice->id,
        'source_device_id' => $sourceDevice->id,
        'purpose' => 'status',
        'sequence' => 1,
    ]);

    expect(fn (): VirtualDeviceLink => VirtualDeviceLink::query()->create([
        'virtual_device_id' => $virtualDevice->id,
        'source_device_id' => $sourceDevice->id,
        'purpose' => 'status',
        'sequence' => 2,
    ]))->toThrow(QueryException::class);
});
