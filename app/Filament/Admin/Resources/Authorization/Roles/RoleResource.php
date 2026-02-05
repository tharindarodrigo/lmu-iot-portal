<?php

namespace App\Filament\Admin\Resources\Authorization\Roles;

use App\Domain\Authorization\Models\Role;
use App\Filament\Admin\Resources\Authorization\Roles\Pages\CreateRole;
use App\Filament\Admin\Resources\Authorization\Roles\Pages\EditRole;
use App\Filament\Admin\Resources\Authorization\Roles\Pages\ListRoles;
use App\Filament\Admin\Resources\Authorization\Roles\Pages\ViewRole;
use App\Filament\Admin\Resources\Authorization\Roles\RelationManagers\PermissionsRelation;
use App\Filament\Admin\Resources\Authorization\Roles\Schemas\RoleForm;
use App\Filament\Admin\Resources\Authorization\Roles\Schemas\RoleInfolist;
use App\Filament\Admin\Resources\Authorization\Roles\Tables\RoleTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    public static function getNavigationGroup(): ?string
    {
        return __('Access Control');
    }

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RoleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoleTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PermissionsRelation::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
