<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AutomationNotificationProfiles;

use App\Domain\Automation\Models\AutomationNotificationProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AutomationNotificationProfileResource extends Resource
{
    protected static ?string $model = AutomationNotificationProfile::class;

    protected static ?string $slug = 'notification-profiles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Alerts');
    }

    public static function getNavigationLabel(): string
    {
        return __('Notification Profiles');
    }

    public static function form(Schema $schema): Schema
    {
        return Schemas\AutomationNotificationProfileForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return Schemas\AutomationNotificationProfileInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return Tables\AutomationNotificationProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAutomationNotificationProfiles::route('/'),
            'create' => Pages\CreateAutomationNotificationProfile::route('/create'),
            'view' => Pages\ViewAutomationNotificationProfile::route('/{record}'),
            'edit' => Pages\EditAutomationNotificationProfile::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
