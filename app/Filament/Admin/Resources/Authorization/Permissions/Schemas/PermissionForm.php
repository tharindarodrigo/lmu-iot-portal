<?php

namespace App\Filament\Admin\Resources\Authorization\Permissions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PermissionForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var array<string, string> $guards */
        $guards = config('enum-permission.guards', []);

        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Permission Name'))
                    ->required()
                    ->maxLength(255)
                    ->live(),
                Select::make('guard_name')
                    ->label(__('Guard Name'))
                    ->options($guards)
                    ->default('web')
                    ->required(),
                TextInput::make('group')
                    ->label(__('Group'))
                    ->required()
                    ->maxLength(255)
                    ->live(),
            ]);
    }
}
