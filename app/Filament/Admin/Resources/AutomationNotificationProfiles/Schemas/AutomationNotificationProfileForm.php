<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AutomationNotificationProfiles\Schemas;

use App\Domain\Shared\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AutomationNotificationProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Profile')
                    ->schema([
                        Select::make('organization_id')
                            ->relationship('organization', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('users', [])),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('channel')
                            ->options([
                                'sms' => 'SMS',
                                'email' => 'Email',
                            ])
                            ->required()
                            ->default('sms')
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('users', [])),
                        Toggle::make('enabled')
                            ->default(true),
                        Select::make('users')
                            ->label('Recipients')
                            ->multiple()
                            ->relationship(
                                name: 'users',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query, Get $get): void {
                                    $organizationId = $get('organization_id');
                                    $channel = $get('channel');

                                    if (! is_numeric($organizationId)) {
                                        $query->whereRaw('1 = 0');

                                        return;
                                    }

                                    $query
                                        ->whereHas('organizations', fn (Builder $organizationQuery): Builder => $organizationQuery->whereKey((int) $organizationId))
                                        ->when(
                                            $channel === 'sms',
                                            fn (Builder $smsQuery): Builder => $smsQuery->whereNotNull('phone_number'),
                                            fn (Builder $emailQuery): Builder => $emailQuery->whereNotNull('email'),
                                        )
                                        ->orderBy('name');
                                },
                            )
                            ->getOptionLabelFromRecordUsing(function (User $record, Get $get): string {
                                $route = $get('channel') === 'sms'
                                    ? ($record->phone_number ?: 'No phone number')
                                    : $record->email;

                                return "{$record->name} ({$route})";
                            })
                            ->helperText('Recipients are organization users. SMS uses the user phone number; email uses the user email address.')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Template')
                    ->schema([
                        TextInput::make('subject')
                            ->helperText('Required for email delivery. Optional for SMS; managed workflows will fall back to a default audit subject.')
                            ->required(fn (Get $get): bool => $get('channel') === 'email'),
                        TextInput::make('mask')
                            ->visible(fn (Get $get): bool => $get('channel') === 'sms'),
                        TextInput::make('campaign_name')
                            ->label('Campaign name')
                            ->visible(fn (Get $get): bool => $get('channel') === 'sms'),
                        Textarea::make('body')
                            ->rows(8)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
