<?php

namespace App\Filament\Admin\Resources\Authorization\Roles\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Role Name')
                            ->weight('bold'),
                        TextEntry::make('guard_name')
                            ->label('Guard Name')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'web' => 'primary',
                                'api' => 'info',
                                default => 'secondary',
                            }),
                        TextEntry::make('organization.name')
                            ->label('Organization'),
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                    ]),
            ]);
    }
}
