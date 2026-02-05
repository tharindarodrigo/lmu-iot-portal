<?php

namespace App\Filament\Admin\Resources\Shared\Organizations\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganizationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Organization Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Organization Name')
                            ->weight('bold'),
                        TextEntry::make('slug')
                            ->label('Slug')
                            ->copyable(),
                        ImageEntry::make('logo')
                            ->label('Logo')
                            ->columnSpanFull(),
                        TextEntry::make('users_count')
                            ->label('Total Users')
                            ->counts('users')
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ]),
            ]);
    }
}
