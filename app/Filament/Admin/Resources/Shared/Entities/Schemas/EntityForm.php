<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Shared\Entities\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EntityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('General')
                    ->schema([
                        Select::make('organization_id')
                            ->label('Organization')
                            ->relationship('organization', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Select organization')
                            ->columnSpanFull(),

                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),

                        Select::make('parent_id')
                            ->label('Parent')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Optional parent')
                            ->nullable(),

                        TextInput::make('icon')
                            ->label('Icon')
                            ->maxLength(255)
                            ->nullable(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
