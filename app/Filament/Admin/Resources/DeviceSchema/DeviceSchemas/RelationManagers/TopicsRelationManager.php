<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\RelationManagers;

use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Enums\TopicPurpose;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\DeviceSchemaVersionResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TopicsRelationManager extends RelationManager
{
    protected static string $relationship = 'topics';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('schemaVersion.version')
                    ->label('Schema Version')
                    ->sortable()
                    ->url(fn (SchemaVersionTopic $record): ?string => $record->device_schema_version_id
                        ? DeviceSchemaVersionResource::getUrl('view', ['record' => $record->device_schema_version_id])
                        : null),

                TextColumn::make('key')
                    ->searchable(),

                TextColumn::make('label')
                    ->searchable(),

                TextColumn::make('direction')
                    ->badge()
                    ->color(fn (TopicDirection $state): array => match ($state) {
                        TopicDirection::Publish => Color::Blue,
                        TopicDirection::Subscribe => Color::Orange,
                    }),

                TextColumn::make('purpose')
                    ->badge()
                    ->formatStateUsing(fn (TopicPurpose|string|null $state): string => $state instanceof TopicPurpose
                        ? $state->label()
                        : (is_string($state) ? (TopicPurpose::tryFrom($state)?->label() ?? $state) : '—')),

                TextColumn::make('parameters_count')
                    ->label('Parameters')
                    ->counts('parameters')
                    ->sortable(),

                IconColumn::make('retain')
                    ->boolean(),
            ])
            ->defaultSort('sequence');
    }
}
