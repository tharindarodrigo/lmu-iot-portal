<?php

namespace App\Filament\Admin\Resources\Authorization\Roles\Tables;

use App\Domain\Authorization\Models\Role;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Role::query())
            ->columns([
                TextColumn::make('name')
                    ->label(__('Role Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('guard_name')
                    ->label(__('Guard Name'))
                    ->badge()
                    ->color(fn (Role $record) => match ($record->guard_name) {
                        'web' => 'primary',
                        'api' => 'info',
                        default => 'secondary',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('organization.name')
                    ->label(__('Organization'))
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->label(__('Organization'))
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->groups([
                'organization.name',
            ]);
    }
}
