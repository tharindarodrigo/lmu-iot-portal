<?php

declare(strict_types=1);

it('configures filament echo for reverb websocket-only transports', function (): void {
    $echoConfiguration = config('filament.broadcasting.echo');

    expect($echoConfiguration)->toBeArray()
        ->and($echoConfiguration['broadcaster'] ?? null)->toBe('reverb')
        ->and($echoConfiguration['enabledTransports'] ?? null)->toBe(['ws', 'wss']);
});
