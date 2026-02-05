<?php

namespace App\Filament\Admin\Resources\Authorization\Permissions\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PermissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Permission Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Permission Name')
                            ->weight('bold'),
                        TextEntry::make('guard_name')
                            ->label('Guard Name')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'web' => 'primary',
                                'api' => 'info',
                                default => 'secondary',
                            }),
                        TextEntry::make('group')
                            ->label('Permission Group')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                    ]),
                Section::make('Assigned Roles')
                    ->schema([
                        RepeatableEntry::make('roles')
                            ->label('Roles with this Permission')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Role Name'),
                                TextEntry::make('guard_name')
                                    ->label('Guard')
                                    ->badge(),
                                TextEntry::make('organization.name')
                                    ->label('Organization'),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }
}
