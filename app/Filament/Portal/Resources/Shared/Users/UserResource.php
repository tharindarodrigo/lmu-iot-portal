<?php

namespace App\Filament\Portal\Resources\Shared\Users;

use App\Domain\Authorization\Models\Role;
use App\Domain\Shared\Models\User;
use App\Filament\Portal\Resources\Shared\Users\Pages\CreateUser;
use App\Filament\Portal\Resources\Shared\Users\Pages\EditUser;
use App\Filament\Portal\Resources\Shared\Users\Pages\ListUsers;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use STS\FilamentImpersonate\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $tenantOwnershipRelationshipName = 'organizations';

    public static function form(Schema $schema): Schema
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
                            ->unique(static::getModel(), 'email', ignorable: fn ($record) => $record)
                            ->live(),
                        Select::make('roles')
                            ->afterStateHydrated(function (User $record, $set): void {
                                // @phpstan-ignore if.alwaysTrue
                                if ($record) {
                                    $users = $record->load('roles');
                                    $roleIds = $users->roles->pluck('id')->toArray();
                                    $set('roles', $roleIds);
                                }
                            })
                            ->options(fn () => Role::query()
                                    // @phpstan-ignore property.nonObject
                                ->where('organization_id', Filament::getTenant()->id)
                                ->pluck('name', 'id')->toArray()
                            )
                            ->preload()
                            ->multiple()
                            ->required(),
                        TextInput::make('password')
                            ->visibleOn(['create'])
                            ->required()
                            ->password()
                            ->live(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

            ])
            ->filters([
                //
            ])
            ->recordActions([
                Impersonate::make()->authorize(function (User $record) {
                    /** @var User $authUser */
                    $authUser = Auth::user();

                    return $authUser->isSuperAdmin() && ! $record->isSuperAdmin();
                }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('assign-role')
                        ->label('Assign Role')
                        ->form(function (Schema $schema) {
                            return $schema
                                ->components([
                                    Select::make('roles')
                                        // ->relationship('roles', 'name')
                                        // ->preload()
                                        ->searchable()
                                        ->options(fn () => Role::query()->pluck('name', 'id')->toArray())
                                        ->required(),
                                ]);
                        }),
                ]),
            ]);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
