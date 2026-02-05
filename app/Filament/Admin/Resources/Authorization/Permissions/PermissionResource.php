<?php

namespace App\Filament\Admin\Resources\Authorization\Permissions;

use App\Filament\Admin\Resources\Authorization\Permissions\Pages\ListPermissions;
use App\Filament\Admin\Resources\Authorization\Permissions\Pages\ViewPermission;
use App\Filament\Admin\Resources\Authorization\Permissions\Schemas\PermissionForm;
use App\Filament\Admin\Resources\Authorization\Permissions\Schemas\PermissionInfolist;
use App\Filament\Admin\Resources\Authorization\Permissions\Tables\PermissionTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    public static function getNavigationGroup(): ?string
    {
        return __('Access Control');
    }

    public static function form(Schema $schema): Schema
    {
        return PermissionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PermissionInfolist::configure($schema);
    }

    /**
     * @param  array<string, mixed>  $tableFilters
     */
    public static function table(Table $table, string $viewType = 'grid', array $tableFilters = []): Table
    {
        return PermissionTable::configure($table, $viewType, $tableFilters);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermissions::route('/'),
            'view' => ViewPermission::route('/{record}'),
            // 'create' => Pages\CreatePermission::route('/create'),
            // 'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
