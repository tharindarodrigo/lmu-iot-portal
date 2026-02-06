<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoT\DeviceSchemaVersions\RelationManagers;

use App\Domain\IoT\Enums\ParameterDataType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ParameterDefinitionsRelationManager extends RelationManager
{
    protected static string $relationship = 'parameters';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required()
                    ->maxLength(100),

                TextInput::make('label')
                    ->required()
                    ->maxLength(255),

                TextInput::make('json_path')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Example: $.status.temp or temp'),

                Select::make('type')
                    ->options(ParameterDataType::class)
                    ->required(),

                TextInput::make('unit')
                    ->maxLength(50)
                    ->placeholder('Celsius'),

                Toggle::make('required')
                    ->label('Required'),

                Toggle::make('is_critical')
                    ->label('Critical'),

                CodeEditor::make('validation_rules')
                    ->language(Language::Json)
                    ->columnSpanFull()
                    ->helperText('JSON rules, e.g. {"min": -40, "max": 85}')
                    ->formatStateUsing(function (mixed $state): ?string {
                        if (is_array($state)) {
                            $encoded = json_encode($state, JSON_PRETTY_PRINT);

                            return $encoded === false ? null : $encoded;
                        }

                        return is_string($state) ? $state : null;
                    })
                    ->dehydrateStateUsing(fn (?string $state): mixed => $state ? json_decode($state, true) : null),

                TextInput::make('validation_error_code')
                    ->maxLength(100)
                    ->placeholder('TEMP_RANGE'),

                CodeEditor::make('mutation_expression')
                    ->language(Language::Json)
                    ->columnSpanFull()
                    ->helperText('JsonLogic expression')
                    ->formatStateUsing(function (mixed $state): ?string {
                        if (is_array($state)) {
                            $encoded = json_encode($state, JSON_PRETTY_PRINT);

                            return $encoded === false ? null : $encoded;
                        }

                        return is_string($state) ? $state : null;
                    })
                    ->dehydrateStateUsing(fn (?string $state): mixed => $state ? json_decode($state, true) : null),

                TextInput::make('sequence')
                    ->integer()
                    ->minValue(0)
                    ->default(0),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('key')
            ->columns([
                TextColumn::make('key')
                    ->searchable(),

                TextColumn::make('label')
                    ->searchable(),

                TextColumn::make('type')
                    ->formatStateUsing(fn (ParameterDataType|string $state): string => $state instanceof ParameterDataType ? $state->label() : (string) $state)
                    ->badge(),

                IconColumn::make('required')
                    ->boolean(),

                IconColumn::make('is_critical')
                    ->label('Critical')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('sequence')
                    ->sortable(),
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
