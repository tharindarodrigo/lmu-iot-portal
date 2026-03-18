<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\RelationManagers;

use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\DeviceTypeResource;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\DeviceSchemaResource;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\DeviceSchemaVersionResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeviceSchemaVersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('version')
                    ->required()
                    ->integer()
                    ->minValue(1),

                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'archived' => 'Archived',
                    ])
                    ->required()
                    ->default('draft'),

                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),

                CodeEditor::make('firmware_template')
                    ->language(Language::Cpp)
                    ->columnSpanFull()
                    ->helperText('Supports placeholders like {{DEVICE_ID}}, {{CONTROL_TOPIC}}, {{STATE_TOPIC}}, {{MQTT_HOST}}, {{MQTT_FALLBACK_HOST}}, {{MQTT_PORT}}, {{MQTT_USE_TLS}}, {{MQTT_SECURITY_MODE}}, and X.509 certificate placeholders.'),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version')
            ->columns([
                TextColumn::make('schema.deviceType.name')
                    ->label('Device Type')
                    ->url(fn (DeviceSchemaVersion $record): ?string => $record->schema?->device_type_id
                        ? DeviceTypeResource::getUrl('view', ['record' => $record->schema->device_type_id])
                        : null),

                TextColumn::make('schema.name')
                    ->label('Device Schema')
                    ->url(fn (DeviceSchemaVersion $record): ?string => $record->device_schema_id
                        ? DeviceSchemaResource::getUrl('view', ['record' => $record->device_schema_id])
                        : null),

                TextColumn::make('version')
                    ->sortable()
                    ->url(fn (DeviceSchemaVersion $record): ?string => $record->id
                        ? DeviceSchemaVersionResource::getUrl('view', ['record' => $record->id])
                        : null),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'active' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('parameters_count')
                    ->label('Parameters')
                    ->counts('parameters'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
