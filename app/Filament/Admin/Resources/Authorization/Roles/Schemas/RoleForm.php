<?php

namespace App\Filament\Admin\Resources\Authorization\Roles\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var array<string, string> $guards */
        $guards = config('enum-permission.guards', []);

        return $schema
            ->components([
                Section::make(__('Role Details'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Role Name'))
                            ->required()
                            ->maxLength(255)
                            ->live()
                            ->unique(
                                ignorable: fn ($record) => $record,
                                modifyRuleUsing: function (Unique $rule, $get) {
                                    return $rule
                                        ->where('organization_id', $get('organization_id') ?? null)
                                        ->where('guard_name', $get('guard_name') ?? 'web');
                                }),
                        Select::make('guard_name')
                            ->label(__('Guard Name'))
                            ->options($guards)
                            ->default('web')
                            ->required(),
                        Select::make('organization_id')
                            ->label(__('Organization'))
                            ->relationship('organization', 'name')
                            ->preload()
                            ->searchable()
                            ->required(),
                    ]),
                Section::make(__('Permissions'))
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('')
                            ->relationship('permissions', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->guard_name})")
                            ->bulkToggleable()
                            ->searchable()
                            ->required()
                            ->columns(3),
                    ]),
            ]);
    }
}
