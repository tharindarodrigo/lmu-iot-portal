<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="JavaScript-only SCADA demo dashboard with simulated factory devices.">
    <title>SCADA Demo Dashboard</title>

    @if (!app()->runningUnitTests())
        @vite(['resources/css/iot-dashboard/demo-scada.css', 'resources/js/iot-dashboard/demo-scada.js'])
    @endif
</head>

<body>
    <main class="scada-demo" data-scada-demo-root aria-label="SCADA demo dashboard">
        <noscript>
            <section class="scada-demo__noscript">
                <h1>SCADA Demo Dashboard</h1>
                <p>This JavaScript-only demo needs JavaScript enabled to render the simulated factory floor.</p>
            </section>
        </noscript>

        <section class="scada-demo__loading" aria-live="polite">
            <p class="scada-demo__eyebrow">Factory operations</p>
            <h1>SCADA Demo Dashboard</h1>
            <p>Loading simulated devices, factory mimic, shift KPIs, and alarm feed…</p>
        </section>
    </main>
</body>

</html>
