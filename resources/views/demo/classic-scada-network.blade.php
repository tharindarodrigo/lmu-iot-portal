<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
        content="Classic SCADA component network demo dashboard with simulated pumps, valves, tanks, PLCs, and telemetry links.">
    <title>Classic SCADA Network Demo</title>

    @if (!app()->runningUnitTests())
        @vite(['resources/css/iot-dashboard/classic-scada-network.css', 'resources/js/iot-dashboard/classic-scada-network.jsx'])
    @endif
</head>

<body>
    <main class="classic-scada" data-classic-scada-network aria-label="Classic SCADA component network demo">
        <noscript>
            <section class="classic-scada__fallback">
                <h1>Classic SCADA Network Demo</h1>
                <p>This component-network dashboard requires JavaScript to render the simulated SCADA canvas.</p>
            </section>
        </noscript>

        <section class="classic-scada__fallback" aria-live="polite">
            <p>Loading SCADA symbols, pipelines, PLC nodes, and simulated equipment states…</p>
        </section>
    </main>
</body>

</html>
