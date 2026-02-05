<?php

namespace App\Filament\Admin\Resources\Shared\Users\RelationManagers;

use App\Domain\Authorization\Models\Role;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class RoleRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Role Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('organization.name')
                    ->label(__('Organization'))
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        // Get organization from pivot data
                        if (isset($record->pivot->organization_id)) {
                            $org = Organization::find($record->pivot->organization_id);

                            return $org->name ?? 'Unknown Organization';
                        }

                        // Fallback to role's organization
                        return $record->organization->name ?? 'No Organization';
                    }),

                TextColumn::make('guard_name')
                    ->label(__('Guard'))
                    ->badge()
                    ->color(fn (Role $record) => match ($record->guard_name) {
                        'web' => 'primary',
                        'api' => 'info',
                        default => 'secondary',
                    })
                    ->sortable(),

                TextColumn::make('permissions_count')
                    ->label(__('Permissions'))
                    ->getStateUsing(function (Role $record) {
                        return $record->permissions()->count();
                    })
                    ->color('gray'),

                TextColumn::make('created_at')
                    ->label(__('Role Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label(__('Detach Role'))
                    ->modalHeading(__('Detach Role from User'))
                    ->modalSubheading(__('Select a role to detach from this user.')),
            ])
            ->headerActions([
                Action::make('Attach Role')
                    ->modalHeading(__('Attach Role to User'))
                    ->schema([
                        CheckboxList::make('role_id')
                            ->label(__('Role'))
                            ->options(function (RelationManager $livewire) {
                                // Get the Roles of the organizations that the user belongs to and keep only the ones not already attached
                                /** @var User $user */
                                $user = $livewire->ownerRecord;
                                $userOrganizationIds = $user->organizations->pluck('id');

                                // Get roles already attached to this user
                                $attachedRoleIds = $user->roles->pluck('id');

                                // Get available roles from user's organizations that aren't already attached
                                return Role::with('organization')
                                    ->whereIn('organization_id', $userOrganizationIds)
                                    ->whereNotIn('id', $attachedRoleIds)
                                    ->get()
                                    ->mapWithKeys(fn ($role) => [
                                        $role->id => $role->name.' ('.($role->organization->name ?? 'Unknown Organization').')',
                                    ]);
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        /** @var User $user */
                        $user = $livewire->ownerRecord;
                        $roleIds = $data['role_id'];

                        foreach ($roleIds as $roleId) {
                            /** @var Role|null $role */
                            $role = Role::find($roleId);
                            if ($role instanceof Role) {
                                // Check if the role is already assigned to prevent duplicates
                                $exists = DB::table('model_has_roles')
                                    ->where('role_id', $role->id)
                                    ->where('model_type', User::class)
                                    ->where('model_id', $user->id)
                                    ->where('organization_id', $role->organization_id)
                                    ->exists();

                                if (! $exists) {
                                    // Insert directly into model_has_roles table with all required fields
                                    DB::table('model_has_roles')->insert([
                                        'role_id' => $role->id,
                                        'model_type' => User::class,
                                        'model_id' => $user->id,
                                        'organization_id' => $role->organization_id,
                                    ]);
                                }
                            }
                        }

                        // Clear cached permissions and refresh the relationship
                        $user->forgetCachedPermissions();
                        $user->load('roles');

                        // Refresh the page to show updated data
                        $livewire->dispatch('$refresh');
                    }),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->options(function ($livewire): array {
                        /** @var User $user */
                        $user = $livewire->ownerRecord;

                        return $user->organizations
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->query(function ($query, array $data) {
                        if (isset($data['value']) && $data['value'] !== '') {
                            return $query->where('model_has_roles.organization_id', $data['value']);
                        }

                        return $query;
                    })
                    ->label(__('Organization')),

                SelectFilter::make('guard_name')
                    ->options(function (): array {
                        /** @var array<string, string> $guards */
                        $guards = config('enum-permission.guards', []);

                        return $guards;
                    })
                    ->label(__('Guard')),
            ]);
    }
}
