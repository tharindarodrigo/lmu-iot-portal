<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Shared\Users\Shemas;

use App\Domain\Shared\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(),
                        TextInput::make('email')
                            ->required()
                            ->email()
                            ->unique(User::class, 'email', ignorable: fn ($record) => $record)
                            ->live(),
                        TextInput::make('phone_number')
                            ->label('Phone Number')
                            ->helperText('Store mobile numbers in E.164 format, for example +94771234567.')
                            ->tel()
                            ->regex('/^\+[1-9][0-9]{7,14}$/')
                            ->unique(User::class, 'phone_number', ignorable: fn ($record) => $record)
                            ->maxLength(16)
                            ->nullable(),
                        TextInput::make('password')
                            ->visibleOn(['create'])
                            ->required()
                            ->password()
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('password_confirmation')
                            ->visibleOn(['create'])
                            ->required()
                            ->password()
                            ->same('password')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
