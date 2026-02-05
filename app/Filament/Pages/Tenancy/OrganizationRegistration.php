<?php

namespace App\Filament\Pages\Tenancy;

use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OrganizationRegistration extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register Organization';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->string(),
            ]);
    }

    protected function handleRegistration(array $data): Organization
    {
        if (! isset($data['name']) || ! is_string($data['name'])) {
            throw new InvalidArgumentException('The name field is required and must be a string.');
        }

        $name = $data['name'];

        $mutatedData = [
            'name' => $name,
            'slug' => Str::slug($name),
            // 'logo' => $data['logo']->store('logos'),
        ];

        /** @var Organization $organization */
        $organization = Organization::create($mutatedData);

        $organization->users()->attach(auth()->user());
        $superAdmins = User::query()->where('is_super_admin', true)->get();

        $organization->users()->attach($superAdmins);

        return $organization;
    }
}
