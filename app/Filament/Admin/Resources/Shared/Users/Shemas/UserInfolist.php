<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Shared\Users\Shemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name'),
                        TextEntry::make('email')
                            ->label('Email Address')
                            ->copyable(),
                        TextEntry::make('phone_number')
                            ->label('Phone Number')
                            ->copyable()
                            ->placeholder('Not set'),
                        TextEntry::make('email_verified_at')
                            ->label('Email Verified At')
                            ->dateTime()
                            ->placeholder('Not verified'),
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                    ]),
            ]);
    }
}
