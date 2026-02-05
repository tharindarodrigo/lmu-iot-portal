<?php

namespace App\Filament\Admin\Resources\Authorization\Permissions\Tables;

use App\Domain\Authorization\Models\Role;
use App\Domain\Shared\Models\Organization;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

class PermissionTable
{
    /**
     * @param  array<string, mixed>  $tableFilters
     */
    public static function configure(Table $table, string $viewType = 'table', array $tableFilters = []): Table
    {
        return $table
            ->query(Permission::query()->with('roles'))
            ->columns($viewType === 'grid' ? self::getGridColumns($tableFilters) : self::getTableColumns())
            ->contentGrid($viewType === 'grid' ? [
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
                '2xl' => 5,
            ] : null)
            ->groups(self::getGroups())
            ->defaultGroup('guard_name')
            ->filters(self::getFilters())
            ->toolbarActions(self::getBulkActions());
    }

    /**
     * @return array<TextColumn>
     */
    protected static function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->searchable()
                ->sortable(),
            TextColumn::make('guard_name')
                ->searchable()
                ->sortable()
                ->badge()
                ->color(fn (Permission $record) => match ($record->guard_name) {
                    'web' => 'primary',
                    'api' => 'info',
                    default => 'secondary',
                }),
            TextColumn::make('group')
                ->searchable()
                ->sortable(),
        ];
    }

    /**
     * @param  array<string, mixed>  $tableFilters
     * @return array<Stack>
     */
    protected static function getGridColumns(array $tableFilters = []): array
    {
        // Check if the 'group' filter is active
        $isGroupFiltered = ! empty($tableFilters['group'] ?? null);

        $columns = [];

        // Only show group if not filtering by a specific group
        if (! $isGroupFiltered) {
            $topRow = Split::make([
                TextColumn::make('group')
                    ->badge()
                    ->color('gray')
                    ->size('sm')
                    ->grow(),
                TextColumn::make('guard_name')
                    ->badge()
                    ->color(fn (Permission $record) => match ($record->guard_name) {
                        'web' => 'primary',
                        'api' => 'info',
                        default => 'secondary',
                    })
                    ->alignment('end')
                    ->grow(false),
            ])->from('xs');

            $columns[] = $topRow;
        } else {
            // If group is filtered, just show the guard name on its own line
            $columns[] = TextColumn::make('guard_name')
                ->badge()
                ->color(fn (Permission $record) => match ($record->guard_name) {
                    'web' => 'primary',
                    'api' => 'info',
                    default => 'secondary',
                })
                ->alignment('end');
        }

        $columns[] = TextColumn::make('name')
            // ->weight(FontWeight::Bold)
            // ->size('lg')
            ->searchable();

        return [
            Stack::make($columns)
                ->space(2)
                ->extraAttributes([
                    'class' => 'group relative overflow-hidden rounded-xl bg-white/80 p-4 shadow-sm ring-1 ring-gray-950/10 transition hover:shadow-md hover:ring-gray-950/20 dark:bg-gray-900/60 dark:ring-white/10 dark:hover:ring-white/20',
                ]),
        ];
    }

    /**
     * @return array<Group>
     */
    protected static function getGroups(): array
    {
        return [
            Group::make('guard_name')
                ->label(__('Guard Name'))
                ->collapsible(),
            Group::make('group')
                ->label(__('Permission Group'))
                ->collapsible(),
        ];
    }

    /**
     * @return array<SelectFilter>
     */
    protected static function getFilters(): array
    {
        return [
            SelectFilter::make('guard_name')
                ->options(function (): array {
                    /** @var array<string, string> $guards */
                    $guards = config('enum-permission.guards', []);

                    return $guards;
                })
                ->label(__('Guard Name'))
                ->placeholder(__('Select Guard Name')),
            SelectFilter::make('group')
                ->options(fn (): array => Permission::select('group')->distinct()->whereNotNull('group')->get()->pluck('group', 'group')->toArray())
                ->label(__('Group'))
                ->multiple()
                ->placeholder(__('Select Group')),
        ];
    }

    /**
     * @return array<BulkActionGroup>
     */
    protected static function getBulkActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make(),
                BulkAction::make('assign-to-role')
                    ->schema(function (Schema $schema) {
                        return $schema
                            ->components([
                                Select::make('organization_id')
                                    ->label(__('Organization'))
                                    ->options(Organization::query()->pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live(),
                                Select::make('role')
                                    ->label(__('Role'))
                                    ->options(function (callable $get) {
                                        $organizationId = $get('organization_id');
                                        if (! $organizationId) {
                                            return [];
                                        }

                                        return Role::query()
                                            ->where('organization_id', $organizationId)
                                            ->pluck('name', 'id')
                                            ->toArray();
                                    })
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                            ]);
                    })
                    ->action(function (Collection $records, array $data): void {
                        $role = Role::find($data['role']);
                        if (! $role) {
                            return;
                        }
                        $role->permissions()->syncWithoutDetaching($records->pluck('id'));
                        Notification::make()
                            ->title(__('Permissions assigned successfully'))
                            ->success()
                            ->send();
                    }),
            ]),
        ];
    }
}
