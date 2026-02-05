<?php

namespace App\Filament\Admin\Resources\Authorization\Permissions\Pages;

use App\Filament\Admin\Resources\Authorization\Permissions\PermissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Livewire\Attributes\Url;

class ListPermissions extends ListRecords
{
    protected static string $resource = PermissionResource::class;

    #[Url]
    public string $viewType = 'grid';

    public function updatedViewType(): void
    {
        $this->resetTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggle_view')
                ->label(fn () => $this->viewType === 'grid' ? 'Table View' : 'Grid View')
                ->icon(fn () => $this->viewType === 'grid' ? 'heroicon-o-table-cells' : 'heroicon-o-squares-2x2')
                ->action(function (): void {
                    $this->viewType = $this->viewType === 'grid' ? 'table' : 'grid';

                    $this->resetTable();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return static::getResource()::table($table, $this->viewType, $this->getTableFilters());
    }
}
