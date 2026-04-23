<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Shared\Entities;

use App\Domain\Shared\Models\Entity;
use App\Filament\Admin\Resources\Shared\Entities\Schemas\EntityForm;
use App\Filament\Admin\Resources\Shared\Entities\Tables\EntityTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EntityResource extends Resource
{
    protected static ?string $model = Entity::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    public static function getNavigationGroup(): ?string
    {
        return __('Organization');
    }

    public static function getNavigationLabel(): string
    {
        return __('Entities');
    }

    public static function form(Schema $schema): Schema
    {
        return EntityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EntityTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntities::route('/'),
            'create' => Pages\CreateEntity::route('/create'),
            'view' => Pages\ViewEntity::route('/{record}'),
            'edit' => Pages\EditEntity::route('/{record}/edit'),
        ];
    }
}
