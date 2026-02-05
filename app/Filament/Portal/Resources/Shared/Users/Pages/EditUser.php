<?php

namespace App\Filament\Portal\Resources\Shared\Users\Pages;

use App\Domain\Authorization\Models\Role;
use App\Domain\Shared\Models\User;
use App\Filament\Portal\Resources\Shared\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $user */
        $user = $record;

        if (! empty($data['roles'])) {
            $roles = Role::whereIn('id', $data['roles'])->get();
            $user->syncRoles($roles);
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        return $record;
    }
}
