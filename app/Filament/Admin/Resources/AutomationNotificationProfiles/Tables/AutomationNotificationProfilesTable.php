<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AutomationNotificationProfiles\Tables;

use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AutomationNotificationProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('organization.name')
                    ->label('Organization')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('channel')
                    ->badge()
                    ->sortable(),
                IconColumn::make('enabled')
                    ->boolean()
                    ->label('Enabled'),
                TextColumn::make('recipient_count')
                    ->label('Recipients')
                    ->state(fn ($record): int => $record->recipientCount()),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\ForceDeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
