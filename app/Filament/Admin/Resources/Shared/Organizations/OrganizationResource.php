<?php

namespace App\Filament\Admin\Resources\Shared\Organizations;

use App\Domain\Shared\Models\Organization;
use App\Filament\Admin\Resources\Shared\Organizations\Pages\CreateOrganization;
use App\Filament\Admin\Resources\Shared\Organizations\Pages\EditOrganization;
use App\Filament\Admin\Resources\Shared\Organizations\Pages\ListOrganizations;
use App\Filament\Admin\Resources\Shared\Organizations\Pages\ViewOrganization;
use App\Filament\Admin\Resources\Shared\Organizations\RelationManagers\UserRelationManager;
use App\Filament\Admin\Resources\Shared\Organizations\Schemas\OrganizationForm;
use App\Filament\Admin\Resources\Shared\Organizations\Schemas\OrganizationInfolist;
use App\Filament\Admin\Resources\Shared\Organizations\Tables\OrganizationTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return OrganizationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrganizationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganizationTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UserRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'create' => CreateOrganization::route('/create'),
            'view' => ViewOrganization::route('/{record}'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }
}
