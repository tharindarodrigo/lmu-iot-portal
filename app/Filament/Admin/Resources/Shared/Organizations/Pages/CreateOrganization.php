<?php

namespace App\Filament\Admin\Resources\Shared\Organizations\Pages;

use App\Filament\Admin\Resources\Shared\Organizations\OrganizationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;
}
