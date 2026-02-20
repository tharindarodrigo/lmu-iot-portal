<?php

declare(strict_types=1);

use App\Providers\Filament\AdminPanelProvider;
use Filament\Panel;

it('keeps filament panel echo bootstrapping enabled', function (): void {
    $panel = (new AdminPanelProvider(app()))->panel(Panel::make());

    expect($panel->hasBroadcasting())->toBeTrue();
});
