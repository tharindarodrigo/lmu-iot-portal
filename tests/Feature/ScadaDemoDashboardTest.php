<?php

declare(strict_types=1);

it('renders the JavaScript-only SCADA demo dashboard shell', function (): void {
    $response = $this->get(route('demo.scada-dashboard'));

    $response->assertSuccessful()
        ->assertSee('SCADA Demo Dashboard')
        ->assertSee('data-scada-demo-root', false)
        ->assertSee('JavaScript-only SCADA demo dashboard with simulated factory devices.');
});

it('renders the classic SCADA component network dashboard shell', function (): void {
    $response = $this->get(route('demo.classic-scada-network'));

    $response->assertSuccessful()
        ->assertSee('Classic SCADA Network Demo')
        ->assertSee('data-classic-scada-network', false)
        ->assertSee('Classic SCADA component network demo dashboard with simulated pumps, valves, tanks, PLCs, and telemetry links.');
});
