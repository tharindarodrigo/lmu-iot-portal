<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\RelationManagers;

use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Enums\MetricUnit;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DerivedParameterDefinition;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn as RepeaterTableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeviceSchemasRelationManager extends RelationManager
{
    protected static string $relationship = 'schemas';

    public function getOwnerRecord(): DeviceType
    {
        /** @var DeviceType $ownerRecord */
        $ownerRecord = $this->ownerRecord;

        return $ownerRecord;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Name for this device schema contract'),

                Toggle::make('create_initial_version')
                    ->label('Create Initial Version')
                    ->default(true)
                    ->helperText('Creates v1 immediately so the device type is ready for onboarding.'),

                Select::make('initial_version_status')
                    ->label('Initial Version Status')
                    ->options([
                        'active' => 'Active',
                        'draft' => 'Draft',
                    ])
                    ->default('active')
                    ->visible(fn (Get $get): bool => (bool) $get('create_initial_version')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('versions_count')
                    ->label('Versions')
                    ->counts('versions'),

                TextColumn::make('active_versions_count')
                    ->label('Active Versions')
                    ->state(fn (DeviceSchema $record): int => $record->versions()->where('status', 'active')->count()),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): DeviceSchema {
                        $schema = $this->getOwnerRecord()->schemas()->create([
                            'name' => (string) $data['name'],
                        ]);

                        $shouldCreateVersion = (bool) ($data['create_initial_version'] ?? true);

                        if ($shouldCreateVersion) {
                            $status = (string) ($data['initial_version_status'] ?? 'active');

                            $schema->versions()->create([
                                'version' => 1,
                                'status' => in_array($status, ['active', 'draft'], true) ? $status : 'active',
                                'notes' => 'Initial version created during device type onboarding.',
                            ]);
                        }

                        return $schema;
                    }),
            ])
            ->recordActions([
                Action::make('explore')
                    ->label('Explore')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('7xl')
                    ->modalHeading(fn (DeviceSchema $record): string => "Schema Explorer: {$record->name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema($this->schemaExplorerSchema())
                    ->fillForm(fn (DeviceSchema $record): array => $this->schemaExplorerData($record)),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    private function schemaExplorerSchema(): array
    {
        return [
            Section::make('Schema Overview')
                ->columns(5)
                ->schema([
                    TextInput::make('schema_name')
                        ->label('Schema Name')
                        ->disabled(),
                    TextInput::make('versions_total')
                        ->label('Total Versions')
                        ->disabled(),
                    TextInput::make('active_versions_total')
                        ->label('Active Versions')
                        ->disabled(),
                    TextInput::make('parameter_total')
                        ->label('Parameters')
                        ->disabled(),
                    TextInput::make('derived_parameter_total')
                        ->label('Derived Parameters')
                        ->disabled(),
                ]),
            Section::make('Schema Versions')
                ->schema([
                    Repeater::make('versions')
                        ->label('Versions')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->collapsible()
                        ->collapsed()
                        ->itemNumbers()
                        ->itemLabel(fn (array $state): string => $this->versionItemLabel($state))
                        ->schema([
                            Section::make('Version Summary')
                                ->columns(4)
                                ->schema([
                                    TextInput::make('version_number')
                                        ->label('Version')
                                        ->disabled(),
                                    TextInput::make('status_label')
                                        ->label('Status')
                                        ->disabled(),
                                    TextInput::make('topics_count')
                                        ->label('Topics')
                                        ->disabled(),
                                    TextInput::make('is_active_label')
                                        ->label('Active')
                                        ->disabled(),
                                    TextInput::make('parameters_count')
                                        ->label('Parameters')
                                        ->disabled(),
                                    TextInput::make('derived_parameters_count')
                                        ->label('Derived Parameters')
                                        ->disabled(),
                                    Textarea::make('notes')
                                        ->label('Notes')
                                        ->disabled()
                                        ->rows(2)
                                        ->columnSpanFull()
                                        ->visible(fn (?string $state): bool => filled($state)),
                                ]),
                            Repeater::make('topics')
                                ->label('Topics')
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->collapsible()
                                ->collapsed()
                                ->itemLabel(fn (array $state): string => $this->topicItemLabel($state))
                                ->schema([
                                    Section::make('Topic Details')
                                        ->columns(4)
                                        ->schema([
                                            TextInput::make('label')
                                                ->label('Topic')
                                                ->disabled(),
                                            TextInput::make('key')
                                                ->label('Key')
                                                ->disabled(),
                                            TextInput::make('direction_label')
                                                ->label('Direction')
                                                ->disabled(),
                                            TextInput::make('purpose_label')
                                                ->label('Purpose')
                                                ->disabled(),
                                            TextInput::make('suffix')
                                                ->label('Suffix')
                                                ->disabled(),
                                            TextInput::make('qos_label')
                                                ->label('QoS')
                                                ->disabled(),
                                            Toggle::make('retain')
                                                ->label('Retain')
                                                ->disabled(),
                                            TextInput::make('parameters_count')
                                                ->label('Parameters')
                                                ->disabled(),
                                            Textarea::make('description')
                                                ->label('Description')
                                                ->disabled()
                                                ->rows(2)
                                                ->columnSpanFull()
                                                ->visible(fn (?string $state): bool => filled($state)),
                                        ]),
                                    Repeater::make('parameters')
                                        ->label('Parameters')
                                        ->addable(false)
                                        ->deletable(false)
                                        ->reorderable(false)
                                        ->table([
                                            RepeaterTableColumn::make('Label'),
                                            RepeaterTableColumn::make('Key'),
                                            RepeaterTableColumn::make('Type'),
                                            RepeaterTableColumn::make('JSON Path'),
                                            RepeaterTableColumn::make('Unit'),
                                            RepeaterTableColumn::make('Default'),
                                            RepeaterTableColumn::make('Required'),
                                            RepeaterTableColumn::make('Active'),
                                        ])
                                        ->compact()
                                        ->schema([
                                            TextInput::make('label')
                                                ->hiddenLabel()
                                                ->disabled(),
                                            TextInput::make('key')
                                                ->hiddenLabel()
                                                ->disabled(),
                                            TextInput::make('type_label')
                                                ->hiddenLabel()
                                                ->disabled(),
                                            TextInput::make('json_path')
                                                ->hiddenLabel()
                                                ->disabled(),
                                            TextInput::make('unit_label')
                                                ->hiddenLabel()
                                                ->disabled(),
                                            TextInput::make('default_value_preview')
                                                ->hiddenLabel()
                                                ->disabled(),
                                            TextInput::make('required_label')
                                                ->hiddenLabel()
                                                ->disabled(),
                                            TextInput::make('active_label')
                                                ->hiddenLabel()
                                                ->disabled(),
                                        ]),
                                ]),
                            Repeater::make('derived_parameters')
                                ->label('Derived Parameters')
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->collapsible()
                                ->collapsed()
                                ->itemLabel(fn (array $state): string => $this->derivedParameterItemLabel($state))
                                ->schema([
                                    Section::make('Derived Parameter Details')
                                        ->columns(3)
                                        ->schema([
                                            TextInput::make('label')
                                                ->label('Label')
                                                ->disabled(),
                                            TextInput::make('key')
                                                ->label('Key')
                                                ->disabled(),
                                            TextInput::make('data_type_label')
                                                ->label('Type')
                                                ->disabled(),
                                            TextInput::make('unit_label')
                                                ->label('Unit')
                                                ->disabled(),
                                            TextInput::make('dependencies_label')
                                                ->label('Dependencies')
                                                ->disabled(),
                                            TextInput::make('json_path')
                                                ->label('JSON Path')
                                                ->disabled(),
                                            Textarea::make('expression')
                                                ->label('Expression')
                                                ->disabled()
                                                ->rows(5)
                                                ->columnSpanFull(),
                                        ]),
                                ]),
                        ]),
                ]),
        ];
    }

    /**
     * Build the read-only schema explorer state for nested repeaters.
     *
     * @return array<string, mixed>
     */
    private function schemaExplorerData(DeviceSchema $schema): array
    {
        $schema->load(['versions.topics.parameters', 'versions.derivedParameters']);

        $versions = $schema->versions
            ->sortByDesc('version')
            ->values();

        return [
            'schema_name' => $schema->name,
            'versions_total' => $versions->count(),
            'active_versions_total' => $versions->where('status', 'active')->count(),
            'parameter_total' => $versions->sum(fn (DeviceSchemaVersion $version): int => $version->topics->sum(
                fn (SchemaVersionTopic $topic): int => $topic->parameters->count(),
            )),
            'derived_parameter_total' => $versions->sum(fn (DeviceSchemaVersion $version): int => $version->derivedParameters->count()),
            'versions' => $versions
                ->map(fn (DeviceSchemaVersion $version): array => $this->versionState($version))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function versionState(DeviceSchemaVersion $version): array
    {
        $topics = $version->topics
            ->sortBy('sequence')
            ->values();

        $parameterCount = $topics->sum(fn (SchemaVersionTopic $topic): int => $topic->parameters->count());

        return [
            'version_number' => "v{$version->version}",
            'status_label' => ucfirst((string) $version->status),
            'is_active_label' => $version->status === 'active' ? 'Yes' : 'No',
            'topics_count' => $topics->count(),
            'parameters_count' => $parameterCount,
            'derived_parameters_count' => $version->derivedParameters->count(),
            'notes' => is_string($version->notes) ? $version->notes : null,
            'topics' => $topics
                ->map(fn (SchemaVersionTopic $topic): array => $this->topicState($topic))
                ->all(),
            'derived_parameters' => $version->derivedParameters
                ->sortBy('key')
                ->values()
                ->map(fn (DerivedParameterDefinition $derived): array => $this->derivedParameterState($derived))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function topicState(SchemaVersionTopic $topic): array
    {
        return [
            'label' => $topic->label,
            'key' => $topic->key,
            'direction_label' => $topic->direction->label(),
            'purpose_label' => $topic->resolvedPurpose()->label(),
            'suffix' => $topic->suffix,
            'qos_label' => "QoS {$topic->qos}",
            'retain' => (bool) $topic->retain,
            'description' => is_string($topic->description) ? $topic->description : null,
            'parameters_count' => $topic->parameters->count(),
            'parameters' => $topic->parameters
                ->sortBy('sequence')
                ->values()
                ->map(fn (ParameterDefinition $parameter): array => $this->parameterState($parameter))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parameterState(ParameterDefinition $parameter): array
    {
        return [
            'label' => $parameter->label,
            'key' => $parameter->key,
            'type_label' => $this->formatDataTypeLabel($parameter->type),
            'json_path' => $parameter->json_path,
            'unit_label' => $this->formatUnit($parameter->unit),
            'default_value_preview' => $this->formatCompactValue($parameter->getAttribute('default_value')),
            'required_label' => $parameter->required ? 'Yes' : 'No',
            'active_label' => $parameter->is_active ? 'Yes' : 'No',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function derivedParameterState(DerivedParameterDefinition $derivedParameter): array
    {
        return [
            'label' => $derivedParameter->label,
            'key' => $derivedParameter->key,
            'data_type_label' => $this->formatDataTypeLabel($derivedParameter->data_type),
            'unit_label' => $this->formatUnit($derivedParameter->unit),
            'dependencies_label' => $this->formatDependencies($derivedParameter->resolvedDependencies()),
            'json_path' => $this->filledOrDash($derivedParameter->json_path),
            'expression' => $this->formatJson($derivedParameter->getAttribute('expression')),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function versionItemLabel(array $state): string
    {
        return implode(' | ', [
            $this->stringState($state, 'version_number', 'Version'),
            $this->stringState($state, 'status_label', 'Unknown'),
            sprintf('%d topic%s', $this->intState($state, 'topics_count'), $this->intState($state, 'topics_count') === 1 ? '' : 's'),
            sprintf('%d parameter%s', $this->intState($state, 'parameters_count'), $this->intState($state, 'parameters_count') === 1 ? '' : 's'),
            sprintf('%d derived parameter%s', $this->intState($state, 'derived_parameters_count'), $this->intState($state, 'derived_parameters_count') === 1 ? '' : 's'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function topicItemLabel(array $state): string
    {
        return implode(' | ', [
            $this->stringState($state, 'label', $this->stringState($state, 'key', 'Topic')),
            $this->stringState($state, 'direction_label', 'Unknown direction'),
            sprintf('%d parameter%s', $this->intState($state, 'parameters_count'), $this->intState($state, 'parameters_count') === 1 ? '' : 's'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function derivedParameterItemLabel(array $state): string
    {
        return implode(' | ', array_filter([
            $this->stringState($state, 'label', $this->stringState($state, 'key', 'Derived parameter')),
            $this->stringState($state, 'key'),
            $this->stringState($state, 'dependencies_label'),
        ]));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function stringState(array $state, string $key, string $fallback = ''): string
    {
        $value = $state[$key] ?? null;

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function intState(array $state, string $key, int $fallback = 0): int
    {
        $value = $state[$key] ?? null;

        return is_numeric($value) ? (int) $value : $fallback;
    }

    private function formatUnit(mixed $unit): string
    {
        if (! is_string($unit) || trim($unit) === '') {
            return '—';
        }

        return MetricUnit::tryFrom($unit)?->label() ?? $unit;
    }

    private function formatDataTypeLabel(mixed $dataType): string
    {
        if ($dataType instanceof ParameterDataType) {
            return $dataType->label();
        }

        if (is_string($dataType)) {
            return ParameterDataType::tryFrom($dataType)?->label() ?? $dataType;
        }

        return '—';
    }

    private function formatCompactValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value);

        return is_string($encoded) ? $encoded : '—';
    }

    /**
     * @param  array<int, string>  $dependencies
     */
    private function formatDependencies(array $dependencies): string
    {
        return $dependencies === [] ? '—' : implode(', ', $dependencies);
    }

    private function formatJson(mixed $value): string
    {
        if (! is_array($value)) {
            return '—';
        }

        $encoded = json_encode($value, JSON_PRETTY_PRINT);

        return is_string($encoded) ? $encoded : '—';
    }

    private function filledOrDash(mixed $value): string
    {
        return is_string($value) && trim($value) !== ''
            ? $value
            : '—';
    }
}
