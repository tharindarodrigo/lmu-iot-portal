<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\Services\VirtualStandardProfileRegistry;
use App\Domain\DeviceManagement\ValueObjects\VirtualStandards\VirtualStandardProfile;
use App\Domain\DeviceManagement\ValueObjects\VirtualStandards\VirtualStandardShiftSchedule;
use App\Domain\DeviceManagement\ValueObjects\VirtualStandards\VirtualStandardSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('returns virtual standard profiles as value objects', function (): void {
    DeviceType::factory()->global()->create([
        'key' => 'stenter_line',
        'virtual_standard_profile' => [
            'label' => 'Stenter Standard',
            'description' => 'Managed stenter profile',
            'shift_schedule' => [
                'id' => 'teejay_stenter_06_00',
                'label' => 'Teejay 06:00 Shift',
            ],
            'sources' => [
                'status' => [
                    'label' => 'Status',
                    'required' => true,
                    'allowed_device_type_keys' => ['status'],
                ],
                'energy' => [
                    'label' => 'Energy',
                    'required' => true,
                    'allowed_device_type_keys' => ['energy_meter'],
                ],
                'length' => [
                    'label' => 'Length',
                    'required' => false,
                    'allowed_device_type_keys' => ['fabric_length_counter'],
                ],
            ],
        ],
    ]);

    $registry = app(VirtualStandardProfileRegistry::class);
    $profile = $registry->forDeviceType('stenter_line');

    expect($profile)->toBeInstanceOf(VirtualStandardProfile::class)
        ->and($profile?->shiftSchedule)->toBeInstanceOf(VirtualStandardShiftSchedule::class)
        ->and($profile?->source('status'))->toBeInstanceOf(VirtualStandardSource::class)
        ->and($profile?->label)->toBe('Stenter Standard')
        ->and($profile?->requiredPurposes())->toEqualCanonicalizing(['status', 'energy'])
        ->and($profile?->allowedDeviceTypeKeysForPurpose('length'))->toBe(['fabric_length_counter'])
        ->and($profile?->managedMetadata())->toMatchArray([
            'virtual_standard_profile_key' => 'stenter_line',
            'virtual_standard_profile_label' => 'Stenter Standard',
            'virtual_standard_shift_schedule_id' => 'teejay_stenter_06_00',
        ]);
});
