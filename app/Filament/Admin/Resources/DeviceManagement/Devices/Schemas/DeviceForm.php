<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\Devices\Schemas;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceManagement\Services\VirtualStandardProfileRegistry;
use App\Domain\DeviceManagement\ValueObjects\VirtualStandards\VirtualStandardProfile;
use App\Domain\DeviceManagement\ValueObjects\VirtualStandards\VirtualStandardShiftSchedule;
use App\Domain\DeviceManagement\ValueObjects\VirtualStandards\VirtualStandardSource;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class DeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Identity')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('uuid')
                            ->label('UUID')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($record) => $record !== null),

                        TextInput::make('external_id')
                            ->label('External ID')
                            ->maxLength(255)
                            ->helperText('Product serial number or hardware ID'),
                    ])
                    ->columns(2),

                Section::make('Configuration')
                    ->schema([
                        Select::make('organization_id')
                            ->relationship('organization', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(function (): ?int {
                                $organizationId = request()->query('organization_id');

                                if (is_numeric($organizationId)) {
                                    return (int) $organizationId;
                                }

                                $deviceTypeId = request()->query('device_type_id');

                                if (! is_numeric($deviceTypeId)) {
                                    return null;
                                }

                                $organization = DeviceType::query()
                                    ->whereKey((int) $deviceTypeId)
                                    ->value('organization_id');

                                return is_numeric($organization) ? (int) $organization : null;
                            })
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('device_type_id', null);
                                $set('device_schema_version_id', null);
                                $set('parent_device_id', null);
                                $set('virtual_device_links', []);
                            }),

                        Select::make('device_type_id')
                            ->label('Device Type')
                            ->options(fn (Get $get): array => self::deviceTypeOptions($get))
                            ->required()
                            ->searchable()
                            ->default(function (): ?int {
                                $deviceTypeId = request()->query('device_type_id');

                                return is_numeric($deviceTypeId) ? (int) $deviceTypeId : null;
                            })
                            ->live()
                            ->helperText('Includes global catalog types plus organization-specific types for the selected organization.')
                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                $defaultSchemaVersionId = self::defaultActiveSchemaVersionId($get('device_type_id'));
                                $set('device_schema_version_id', $defaultSchemaVersionId);

                                $profile = self::selectedVirtualStandardProfile($get);

                                if ($profile === null) {
                                    return;
                                }

                                $set('is_virtual', true);
                                $set('parent_device_id', null);

                                $existingLinks = $get('virtual_device_links');

                                if (is_array($existingLinks) && $existingLinks !== []) {
                                    return;
                                }

                                $set('virtual_device_links', self::defaultVirtualDeviceLinksForProfile($profile));
                            }),

                        Select::make('device_schema_version_id')
                            ->label('Schema Version')
                            ->options(fn (Get $get): array => self::schemaVersionOptions($get))
                            ->required()
                            ->searchable()
                            ->default(fn (): ?int => self::defaultActiveSchemaVersionId(request()->query('device_type_id')))
                            ->disabled(fn (Get $get) => ! $get('device_type_id'))
                            ->helperText('Only active schema versions for the selected device type are shown'),

                        Toggle::make('is_virtual')
                            ->label('Virtual Device')
                            ->default(false)
                            ->live()
                            ->helperText(fn (Get $get): string => self::virtualDeviceHelperText($get))
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                if ($state) {
                                    $set('parent_device_id', null);
                                }
                            }),

                        Select::make('parent_device_id')
                            ->label('Parent Hub')
                            ->options(fn (Get $get, ?Device $record): array => self::parentDeviceOptions($get, $record))
                            ->searchable()
                            ->preload()
                            ->placeholder('No parent hub')
                            ->helperText('Assign this physical device to a hub for grouped visibility and health tracking.')
                            ->visible(fn (Get $get): bool => ! (bool) $get('is_virtual'))
                            ->dehydrated(fn (Get $get): bool => ! (bool) $get('is_virtual')),

                        Placeholder::make('onboarding_hint')
                            ->label('Onboarding Hint')
                            ->content(function (Get $get): string {
                                $deviceTypeId = $get('device_type_id');

                                if (! is_numeric($deviceTypeId)) {
                                    return 'Select an organization and device type to load active schema versions.';
                                }

                                $schemaOptions = self::schemaVersionOptions($get);

                                if ($schemaOptions === []) {
                                    return 'This device type has no active schema version yet. Create/activate a schema version before onboarding devices.';
                                }

                                return 'Schema version is preselected to the latest active version. You can still choose another active version.';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Standard Profile')
                    ->visible(fn (Get $get): bool => self::selectedVirtualStandardProfile($get) !== null)
                    ->schema([
                        Placeholder::make('standard_profile_name')
                            ->label('Profile')
                            ->content(function (Get $get): string {
                                $profile = self::selectedVirtualStandardProfile($get);

                                return $profile instanceof VirtualStandardProfile ? $profile->label : '—';
                            }),
                        Placeholder::make('standard_profile_shift_schedule')
                            ->label('Shift Schedule')
                            ->content(fn (Get $get): string => self::selectedVirtualStandardShiftScheduleLabel($get)),
                        Placeholder::make('standard_profile_sources')
                            ->label('Expected Source Purposes')
                            ->content(fn (Get $get): string => self::selectedVirtualStandardPurposeSummary($get))
                            ->columnSpanFull(),
                        Placeholder::make('standard_profile_source_types')
                            ->label('Allowed Source Device Types')
                            ->content(fn (Get $get): string => self::selectedVirtualStandardSourceTypeSummary($get))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Virtual Composition')
                    ->description(fn (Get $get): string => self::virtualCompositionDescription($get))
                    ->visible(fn (Get $get): bool => (bool) $get('is_virtual'))
                    ->schema([
                        Repeater::make('virtual_device_links')
                            ->label('Source Devices')
                            ->default([])
                            ->reorderable()
                            ->addActionLabel('Add Source Device')
                            ->columns(2)
                            ->columnSpanFull()
                            ->itemLabel(function (array $state): ?string {
                                $purpose = trim((string) ($state['purpose'] ?? ''));

                                return $purpose !== '' ? Str::headline($purpose) : null;
                            })
                            ->schema([
                                Hidden::make('id'),
                                TextInput::make('purpose')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('status')
                                    ->helperText(fn (Get $get): string => self::purposeHelperText($get))
                                    ->regex('/^[a-z0-9_-]+$/'),
                                Select::make('source_device_id')
                                    ->label('Source Device')
                                    ->options(fn (Get $get): array => self::sourceDeviceOptions($get))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText(fn (Get $get): string => self::sourceDeviceHelperText($get)),
                            ]),
                    ]),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Is Active')
                            ->default(true),

                        Placeholder::make('effective_connection_state')
                            ->label('Connection State')
                            ->content(fn (?Device $record): string => $record ? Str::headline($record->effectiveConnectionState()) : 'Unknown'),

                        TextInput::make('presence_timeout_seconds')
                            ->label('Presence Timeout (seconds)')
                            ->numeric()
                            ->integer()
                            ->minValue(60)
                            ->maxValue(86400)
                            ->live()
                            ->placeholder('Global fallback (300 seconds)')
                            ->dehydrateStateUsing(fn (mixed $state): ?int => is_numeric($state) ? (int) $state : null)
                            ->helperText('Blank uses the global fallback of 300 seconds.'),

                        Placeholder::make('effective_presence_timeout')
                            ->label('Effective Timeout')
                            ->content(function (Get $get, ?Device $record): string {
                                $configuredTimeout = config('iot.presence.heartbeat_timeout_seconds', 300);
                                $fallbackTimeoutSeconds = is_numeric($configuredTimeout) && (int) $configuredTimeout > 0
                                    ? (int) $configuredTimeout
                                    : 300;
                                $override = $get('presence_timeout_seconds');
                                $effectiveTimeoutSeconds = is_numeric($override) && (int) $override >= 60
                                    ? (int) $override
                                    : ($record?->presenceTimeoutSeconds() ?? $fallbackTimeoutSeconds);

                                return "{$effectiveTimeoutSeconds} seconds";
                            }),

                        TextInput::make('last_seen_at')
                            ->label('Last Seen At')
                            ->disabled()
                            ->placeholder('Never'),
                    ])
                    ->columns(2),

                Section::make('Metadata')
                    ->schema([
                        KeyValue::make('metadata')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function deviceTypeOptions(Get $get): array
    {
        $organizationId = $get('organization_id');

        $query = DeviceType::query()
            ->orderBy('name');

        if (is_numeric($organizationId)) {
            $query->where(function ($scope) use ($organizationId): void {
                $scope
                    ->whereNull('organization_id')
                    ->orWhere('organization_id', (int) $organizationId);
            });
        } else {
            $query->whereNull('organization_id');
        }

        return $query
            ->get(['id', 'name', 'key', 'organization_id', 'virtual_standard_profile'])
            ->mapWithKeys(function (DeviceType $deviceType): array {
                $scopeSuffix = $deviceType->organization_id === null ? ' (Global)' : '';
                $profileSuffix = $deviceType->isVirtualStandard() ? ' · Standard Profile' : '';

                return [
                    (int) $deviceType->id => "{$deviceType->name}{$scopeSuffix}{$profileSuffix}",
                ];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function schemaVersionOptions(Get $get): array
    {
        $deviceTypeId = $get('device_type_id');

        if (! is_numeric($deviceTypeId)) {
            return [];
        }

        return DeviceSchemaVersion::query()
            ->with('schema')
            ->whereHas('schema', fn ($query) => $query->where('device_type_id', (int) $deviceTypeId))
            ->where('status', 'active')
            ->orderByDesc('version')
            ->get(['id', 'device_schema_id', 'version'])
            ->mapWithKeys(function (DeviceSchemaVersion $schemaVersion): array {
                $schemaName = data_get($schemaVersion, 'schema.name', 'Schema');
                $schemaName = is_string($schemaName) && trim($schemaName) !== '' ? $schemaName : 'Schema';
                $version = (string) $schemaVersion->version;

                return [
                    (int) $schemaVersion->id => "{$schemaName} · v{$version}",
                ];
            })
            ->all();
    }

    private static function defaultActiveSchemaVersionId(mixed $deviceTypeId): ?int
    {
        if (! is_numeric($deviceTypeId)) {
            return null;
        }

        $schemaVersion = DeviceSchemaVersion::query()
            ->whereHas('schema', fn ($query) => $query->where('device_type_id', (int) $deviceTypeId))
            ->where('status', 'active')
            ->orderByDesc('version')
            ->first(['id']);

        if ($schemaVersion === null) {
            return null;
        }

        return (int) $schemaVersion->id;
    }

    /**
     * @return array<int, string>
     */
    private static function parentDeviceOptions(Get $get, ?Device $record): array
    {
        $organizationId = $get('organization_id');

        if (! is_numeric($organizationId) && $record?->organization_id === null) {
            return [];
        }

        $resolvedOrganizationId = is_numeric($organizationId)
            ? (int) $organizationId
            : $record?->organization_id;

        if (! is_int($resolvedOrganizationId)) {
            return [];
        }

        $query = Device::query()
            ->where('organization_id', $resolvedOrganizationId)
            ->physical()
            ->whereNull('parent_device_id')
            ->orderBy('name');

        if ($record !== null) {
            $query->whereKeyNot($record->getKey());
        }

        return $query
            ->get(['id', 'name', 'external_id'])
            ->mapWithKeys(function (Device $device): array {
                $externalId = is_string($device->external_id) && trim($device->external_id) !== ''
                    ? " · {$device->external_id}"
                    : '';

                return [
                    (int) $device->id => "{$device->name}{$externalId}",
                ];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function sourceDeviceOptions(Get $get): array
    {
        $organizationId = self::resolvedOrganizationId($get);

        if ($organizationId === null) {
            return [];
        }

        $query = Device::query()
            ->where('organization_id', $organizationId)
            ->physical()
            ->orderBy('name');

        $currentRecordId = self::currentRecordId();

        if ($currentRecordId !== null) {
            $query->whereKeyNot($currentRecordId);
        }

        return $query
            ->get(['id', 'name', 'external_id'])
            ->mapWithKeys(function (Device $device): array {
                $externalId = is_string($device->external_id) && trim($device->external_id) !== ''
                    ? " · {$device->external_id}"
                    : '';

                return [
                    (int) $device->id => "{$device->name}{$externalId}",
                ];
            })
            ->all();
    }

    /**
     * Resolve the selected managed virtual standard profile for the current device type state.
     */
    private static function selectedVirtualStandardProfile(Get $get): ?VirtualStandardProfile
    {
        return self::virtualStandardRegistry()->forDeviceTypeId($get('device_type_id'));
    }

    /**
     * @return array<int, array{purpose: string, source_device_id: null}>
     */
    private static function defaultVirtualDeviceLinksForProfile(VirtualStandardProfile $profile): array
    {
        return collect($profile->purposes())
            ->map(fn (string $purpose): array => [
                'purpose' => $purpose,
                'source_device_id' => null,
            ])
            ->values()
            ->all();
    }

    private static function virtualDeviceHelperText(Get $get): string
    {
        $profile = self::selectedVirtualStandardProfile($get);

        if ($profile === null) {
            return 'Use virtual devices for standards that combine multiple physical devices by purpose.';
        }

        return "{$profile->label} uses a managed standard profile and should stay virtual.";
    }

    private static function virtualCompositionDescription(Get $get): string
    {
        $profile = self::selectedVirtualStandardProfile($get);

        if ($profile === null) {
            return 'Attach the physical source devices that power this virtual device using semantic purposes like status, energy, or length.';
        }

        $purposeList = collect($profile->sources)
            ->map(fn (VirtualStandardSource $source): string => $source->label)
            ->implode(', ');

        return "{$profile->label} expects these source purposes: {$purposeList}.";
    }

    private static function purposeHelperText(Get $get): string
    {
        $profile = self::selectedVirtualStandardProfile($get);

        if ($profile === null) {
            return 'Use lowercase semantic purposes such as status, energy, or length.';
        }

        $purposeList = collect($profile->purposes())
            ->map(fn (string $purpose): string => $purpose)
            ->implode(', ');

        return "Expected purposes for this profile: {$purposeList}.";
    }

    private static function sourceDeviceHelperText(Get $get): string
    {
        $allowedDeviceTypeKeys = self::allowedSourceDeviceTypeKeys($get);

        if ($allowedDeviceTypeKeys === []) {
            return 'Only physical devices from the selected organization can be attached.';
        }

        $allowedDeviceTypes = collect($allowedDeviceTypeKeys)
            ->map(fn (string $key): string => Str::headline($key))
            ->implode(', ');

        return "Only physical devices from the selected organization with these types can be attached: {$allowedDeviceTypes}.";
    }

    /**
     * @return array<int, string>
     */
    private static function allowedSourceDeviceTypeKeys(Get $get): array
    {
        $profile = self::selectedVirtualStandardProfile($get);
        $purpose = self::resolvedPurpose($get);

        if ($profile === null || $purpose === null) {
            return [];
        }

        return self::virtualStandardRegistry()->allowedDeviceTypeKeysForPurpose($profile, $purpose);
    }

    private static function resolvedPurpose(Get $get): ?string
    {
        foreach (['purpose', '../purpose'] as $path) {
            $purpose = $get($path);

            if (is_string($purpose) && trim($purpose) !== '') {
                return trim($purpose);
            }
        }

        return null;
    }

    private static function selectedVirtualStandardShiftScheduleLabel(Get $get): string
    {
        $profile = self::selectedVirtualStandardProfile($get);

        if ($profile === null) {
            return 'Not configured';
        }

        return $profile->shiftSchedule instanceof VirtualStandardShiftSchedule
            ? $profile->shiftSchedule->label
            : 'Not configured';
    }

    private static function selectedVirtualStandardPurposeSummary(Get $get): string
    {
        $profile = self::selectedVirtualStandardProfile($get);

        if ($profile === null) {
            return 'No managed standard profile selected.';
        }

        return collect($profile->sources)
            ->map(function (VirtualStandardSource $source): string {
                $suffix = $source->required ? 'required' : 'optional';

                return sprintf('%s (%s)', $source->label, $suffix);
            })
            ->implode(', ');
    }

    private static function selectedVirtualStandardSourceTypeSummary(Get $get): string
    {
        $profile = self::selectedVirtualStandardProfile($get);

        if ($profile === null) {
            return 'No managed source rules.';
        }

        return collect($profile->sources)
            ->map(function (VirtualStandardSource $source): string {
                $allowedTypes = collect($source->allowedDeviceTypeKeys)
                    ->map(fn (string $key): string => Str::headline($key))
                    ->implode(', ');

                return $source->label.': '.($allowedTypes !== '' ? $allowedTypes : 'Any physical device');
            })
            ->implode(PHP_EOL);
    }

    private static function virtualStandardRegistry(): VirtualStandardProfileRegistry
    {
        return app(VirtualStandardProfileRegistry::class);
    }

    private static function resolvedOrganizationId(Get $get): ?int
    {
        foreach (['organization_id', '../../organization_id'] as $path) {
            $organizationId = $get($path);

            if (is_numeric($organizationId)) {
                return (int) $organizationId;
            }
        }

        return null;
    }

    private static function currentRecordId(): ?int
    {
        $record = request()->route('record');

        if ($record instanceof Device && is_numeric($record->getKey())) {
            return (int) $record->getKey();
        }

        return is_numeric($record) ? (int) $record : null;
    }
}
