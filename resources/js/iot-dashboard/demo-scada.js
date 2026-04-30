const STATUS_META = Object.freeze({
    running: {
        label: 'Running',
        color: '#22c55e',
        live: true,
    },
    idle: {
        label: 'Idle',
        color: '#f59e0b',
        live: false,
    },
    warning: {
        label: 'Warning',
        color: '#f97316',
        live: true,
    },
    fault: {
        label: 'Fault',
        color: '#ef4444',
        live: true,
    },
    maintenance: {
        label: 'Maintenance',
        color: '#a78bfa',
        live: false,
    },
});

const AREA_OPTIONS = Object.freeze([
    { id: 'all', label: 'All Areas' },
    { id: 'production', label: 'Production' },
    { id: 'utilities', label: 'Utilities' },
    { id: 'quality', label: 'Quality' },
]);

const SHIFT_OPTIONS = Object.freeze(['Shift A', 'Shift B', 'Shift C']);

const EVENT_TEMPLATES = Object.freeze([
    ['running', 'Stenter speed stabilized', 'Line speed is within the recipe tolerance window.'],
    ['warning', 'Compressor pressure drift', 'Receiver pressure is trending below target for Area B.'],
    ['running', 'Boiler feed water nominal', 'Steam drum water level returned to normal band.'],
    ['warning', 'Energy demand nearing peak', 'Demand is above the demo threshold for this shift.'],
    ['fault', 'Packing counter reject spike', 'Reject count exceeded the simulated quality limit.'],
    ['maintenance', 'Preventive maintenance window', 'Chiller pump inspection is scheduled in the demo plan.'],
]);

const root = document.querySelector('[data-scada-demo-root]');

const state = {
    paused: false,
    selectedArea: 'all',
    shiftIndex: 1,
    maintenanceDrill: false,
    tick: 0,
    devices: createDevices(),
    events: createInitialEvents(),
};

if (root) {
    render();
    root.addEventListener('click', handleDashboardClick);

    window.setInterval(() => {
        if (state.paused) {
            updateClockOnly();

            return;
        }

        advanceSimulation();
        render();
    }, 1800);
}

function createDevices() {
    return [
        {
            id: 'stenter-01',
            name: 'Stenter Line 01',
            type: 'Stenter',
            area: 'production',
            zone: 'Finishing',
            icon: '🧵',
            status: 'running',
            x: 30,
            y: 42,
            primary: metric('Fabric speed', 63.4, 'm/min', 42, 78, 1, 1.6),
            metrics: [
                metric('Chamber temp', 184, '°C', 165, 205, 0, 2.8),
                metric('Utilization', 88, '%', 54, 96, 0, 1.3),
            ],
            trend: seedTrend(64, 14, 1.6),
        },
        {
            id: 'dye-range-02',
            name: 'Dye Range 02',
            type: 'Process Line',
            area: 'production',
            zone: 'Dye House',
            icon: '🎛️',
            status: 'running',
            x: 52,
            y: 34,
            primary: metric('Bath temp', 91.2, '°C', 72, 98, 1, 1.1),
            metrics: [
                metric('pH value', 6.8, 'pH', 5.8, 8.2, 1, 0.08),
                metric('Batch progress', 74, '%', 12, 100, 0, 1.7),
            ],
            trend: seedTrend(91, 14, 0.9),
        },
        {
            id: 'compressor-room',
            name: 'Compressor Station',
            type: 'Compressed Air',
            area: 'utilities',
            zone: 'Utility',
            icon: '🌀',
            status: 'warning',
            x: 21,
            y: 72,
            primary: metric('Header pressure', 6.8, 'bar', 5.9, 8.2, 1, 0.18),
            metrics: [
                metric('Load', 72, '%', 34, 95, 0, 2.4),
                metric('Power', 126, 'kW', 82, 154, 0, 3.6),
            ],
            trend: seedTrend(6.8, 14, 0.22),
        },
        {
            id: 'boiler-house',
            name: 'Boiler House',
            type: 'Steam',
            area: 'utilities',
            zone: 'Utility',
            icon: '♨️',
            status: 'running',
            x: 47,
            y: 74,
            primary: metric('Steam pressure', 9.4, 'bar', 7.8, 11.2, 1, 0.16),
            metrics: [
                metric('Steam flow', 4.6, 't/h', 2.8, 6.4, 1, 0.16),
                metric('Water level', 66, '%', 48, 82, 0, 1.3),
            ],
            trend: seedTrend(9.4, 14, 0.22),
        },
        {
            id: 'energy-main',
            name: 'Main Energy Meter',
            type: 'Energy',
            area: 'utilities',
            zone: 'Electrical',
            icon: '⚡',
            status: 'running',
            x: 70,
            y: 68,
            primary: metric('Plant demand', 742, 'kW', 580, 920, 0, 18),
            metrics: [
                metric('Power factor', 0.96, 'PF', 0.82, 1, 2, 0.01),
                metric('Today', 12.8, 'MWh', 8.4, 18.6, 1, 0.16),
            ],
            trend: seedTrend(742, 14, 32),
        },
        {
            id: 'chiller-loop',
            name: 'Chiller Loop',
            type: 'Cooling Water',
            area: 'utilities',
            zone: 'Water',
            icon: '💧',
            status: 'running',
            x: 78,
            y: 41,
            primary: metric('Supply temp', 12.4, '°C', 8.5, 16.5, 1, 0.22),
            metrics: [
                metric('Flow', 184, 'm³/h', 120, 230, 0, 3.5),
                metric('Pump load', 61, '%', 32, 86, 0, 1.8),
            ],
            trend: seedTrend(12.4, 14, 0.35),
        },
        {
            id: 'environment-qc',
            name: 'QC Environment',
            type: 'Environment',
            area: 'quality',
            zone: 'Quality',
            icon: '🌡️',
            status: 'running',
            x: 67,
            y: 19,
            primary: metric('Humidity', 58, '%RH', 42, 72, 0, 1.2),
            metrics: [
                metric('Room temp', 27.1, '°C', 23.5, 31.5, 1, 0.18),
                metric('Air quality', 91, 'AQI', 76, 98, 0, 0.9),
            ],
            trend: seedTrend(58, 14, 1.8),
        },
        {
            id: 'packing-counter',
            name: 'Packing Counter',
            type: 'Output',
            area: 'quality',
            zone: 'Packing',
            icon: '📦',
            status: 'idle',
            x: 38,
            y: 18,
            primary: metric('Shift output', 12840, 'm', 9400, 16200, 0, 160),
            metrics: [
                metric('Target', 15000, 'm', 15000, 15000, 0, 0),
                metric('Rejects', 18, 'rolls', 2, 42, 0, 1.2),
            ],
            trend: seedTrend(12840, 14, 320),
        },
    ];
}

function metric(label, value, unit, min, max, precision, volatility) {
    return {
        label,
        value,
        unit,
        min,
        max,
        precision,
        volatility,
    };
}

function seedTrend(base, length, spread) {
    return Array.from({ length }, (_, index) => base + Math.sin(index / 1.65) * spread + randomBetween(-spread, spread) * 0.38);
}

function createInitialEvents() {
    const now = new Date();

    return [
        makeEvent(now, 'running', 'Shift handover complete', 'All production cells accepted the demo shift plan.'),
        makeEvent(new Date(now.getTime() - 240000), 'warning', 'Compressor station watch', 'Pressure recovery is slower than expected.'),
        makeEvent(new Date(now.getTime() - 480000), 'running', 'Steam flow synchronized', 'Boiler and stenter demand are balanced.'),
        makeEvent(new Date(now.getTime() - 720000), 'maintenance', 'Chiller inspection queued', 'Pump vibration check is scheduled after the batch.'),
    ];
}

function handleDashboardClick(event) {
    const actionTarget = event.target.closest('[data-scada-action]');
    const areaTarget = event.target.closest('[data-scada-area]');

    if (actionTarget) {
        const action = actionTarget.dataset.scadaAction;

        if (action === 'toggle-pause') {
            state.paused = !state.paused;
            pushEvent(state.paused ? 'maintenance' : 'running', state.paused ? 'Simulation paused' : 'Simulation resumed', 'The browser-only demo loop was toggled.');
        }

        if (action === 'cycle-shift') {
            state.shiftIndex = (state.shiftIndex + 1) % SHIFT_OPTIONS.length;
            pushEvent('running', `${SHIFT_OPTIONS[state.shiftIndex]} selected`, 'KPIs are now presented against the selected demo shift.');
        }

        if (action === 'toggle-maintenance') {
            state.maintenanceDrill = !state.maintenanceDrill;
            pushEvent(
                state.maintenanceDrill ? 'warning' : 'running',
                state.maintenanceDrill ? 'Maintenance drill enabled' : 'Normal operation restored',
                state.maintenanceDrill ? 'Selected utility devices are placed into a demo watch state.' : 'Demo devices returned to their simulated operating profile.',
            );
        }

        render();

        return;
    }

    if (areaTarget) {
        state.selectedArea = areaTarget.dataset.scadaArea || 'all';
        render();
    }
}

function advanceSimulation() {
    state.tick += 1;

    for (const device of state.devices) {
        advanceDevice(device);
    }

    if (state.maintenanceDrill) {
        applyMaintenanceDrill();
    } else if (state.tick % 5 === 0) {
        rotateDeviceStates();
    }

    if (state.tick % 4 === 0) {
        const [status, title, message] = EVENT_TEMPLATES[Math.floor(Math.random() * EVENT_TEMPLATES.length)];
        pushEvent(status, title, message);
    }
}

function advanceDevice(device) {
    const metrics = [device.primary, ...device.metrics];

    for (const item of metrics) {
        const drift = randomBetween(-item.volatility, item.volatility);
        const statusBias = device.status === 'warning' ? item.volatility * 0.18 : 0;
        const nextValue = clamp(item.value + drift + statusBias, item.min, item.max);
        item.value = roundTo(nextValue, item.precision);
    }

    device.trend = [...device.trend.slice(1), Number(device.primary.value)];
}

function applyMaintenanceDrill() {
    for (const device of state.devices) {
        if (device.id === 'compressor-room') {
            device.status = 'warning';
        } else if (device.id === 'chiller-loop') {
            device.status = 'maintenance';
        } else if (device.id === 'packing-counter') {
            device.status = 'idle';
        } else if (device.status === 'fault') {
            device.status = 'warning';
        }
    }
}

function rotateDeviceStates() {
    const device = state.devices[Math.floor(Math.random() * state.devices.length)];
    const roll = Math.random();

    if (roll > 0.94) {
        device.status = 'fault';
    } else if (roll > 0.76) {
        device.status = 'warning';
    } else if (roll > 0.62) {
        device.status = 'idle';
    } else {
        device.status = 'running';
    }
}

function render() {
    root.innerHTML = `
        <section class="scada-demo__shell">
            ${renderHero()}
            ${renderKpis()}
            <section class="scada-demo__main-grid">
                ${renderMimicPanel()}
                <aside class="scada-side-stack" aria-label="Shift and event summaries">
                    ${renderShiftPanel()}
                    ${renderEventPanel()}
                </aside>
            </section>
            ${renderDeviceGrid()}
            ${renderFooter()}
        </section>
    `;
}

function renderHero() {
    return `
        <header class="scada-demo__hero">
            <div class="scada-demo__hero-copy">
                <p class="scada-demo__eyebrow">Factory operations • browser-only simulation</p>
                <h1>SCADA Demo Dashboard</h1>
                <p>Live-looking plant overview with textile production, utilities, quality, and alarm storytelling powered entirely by JavaScript mock data.</p>
            </div>
            <div class="scada-demo__hero-controls" aria-label="Demo controls">
                <div class="scada-demo__clock">
                    <span>Plant time</span>
                    <strong data-scada-clock>${formatClock(new Date())}</strong>
                </div>
                <div class="scada-demo__controls">
                    <button class="scada-demo__button ${state.paused ? 'is-warning' : 'is-active'}" type="button" data-scada-action="toggle-pause">
                        ${state.paused ? '▶ Resume simulation' : '⏸ Pause simulation'}
                    </button>
                    <button class="scada-demo__button" type="button" data-scada-action="cycle-shift">
                        ${escapeHtml(SHIFT_OPTIONS[state.shiftIndex])}
                    </button>
                    <button class="scada-demo__button ${state.maintenanceDrill ? 'is-danger' : ''}" type="button" data-scada-action="toggle-maintenance">
                        ${state.maintenanceDrill ? '⚠ End drill' : '🛠 Maintenance drill'}
                    </button>
                </div>
                <div class="scada-demo__filters" aria-label="Area filter">
                    ${AREA_OPTIONS.map((area) => `
                        <button class="scada-demo__button ${state.selectedArea === area.id ? 'is-active' : ''}" type="button" data-scada-area="${escapeHtml(area.id)}">
                            ${escapeHtml(area.label)}
                        </button>
                    `).join('')}
                </div>
            </div>
        </header>
    `;
}

function renderKpis() {
    const kpis = calculateKpis();

    return `
        <section class="scada-kpi-grid" aria-label="Plant KPIs">
            ${kpis.map((kpi) => `
                <article class="scada-kpi" style="--kpi-color: ${kpi.color}">
                    <p class="scada-demo__metric-label">${escapeHtml(kpi.label)}</p>
                    <strong>${escapeHtml(kpi.value)}</strong>
                    <small>${escapeHtml(kpi.caption)}</small>
                </article>
            `).join('')}
        </section>
    `;
}

function renderMimicPanel() {
    return `
        <section class="scada-panel" aria-label="Factory floor mimic">
            <div class="scada-panel__header">
                <div>
                    <p class="scada-demo__section-label">SCADA mimic</p>
                    <h2>Textile factory floor overview</h2>
                </div>
                <span class="scada-status-pill is-live" style="--status-color: ${STATUS_META.running.color}">Live demo</span>
            </div>
            <div class="scada-mimic">
                <svg class="scada-mimic__svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                    <path class="scada-mimic__pipe is-utility" d="M21 72 C34 78, 38 76, 47 74 S63 70, 70 68" />
                    <path class="scada-mimic__pipe" d="M47 74 C42 62, 37 51, 30 42 S41 35, 52 34" />
                    <path class="scada-mimic__pipe" d="M78 41 C72 37, 64 35, 52 34" />
                    <path class="scada-mimic__pipe is-warning" d="M38 18 C47 16, 57 16, 67 19" />
                    <path class="scada-mimic__pipe" d="M30 42 C31 30, 34 22, 38 18" />
                </svg>
                ${renderMimicZones()}
                ${state.devices.map(renderMimicNode).join('')}
            </div>
        </section>
    `;
}

function renderMimicZones() {
    return `
        <div class="scada-mimic-zone" style="left: 7%; top: 8%; width: 48%; height: 41%;">Production</div>
        <div class="scada-mimic-zone" style="left: 58%; top: 8%; width: 34%; height: 41%;">Quality / water</div>
        <div class="scada-mimic-zone" style="left: 7%; top: 56%; width: 85%; height: 35%;">Utilities corridor</div>
    `;
}

function renderMimicNode(device) {
    const status = STATUS_META[device.status] || STATUS_META.idle;
    const isDimmed = state.selectedArea !== 'all' && state.selectedArea !== device.area;

    return `
        <article
            class="scada-mimic-node ${status.live ? 'is-active' : ''} ${isDimmed ? 'is-dimmed' : ''}"
            style="left: ${device.x}%; top: ${device.y}%; --status-color: ${status.color}; ${isDimmed ? 'opacity: 0.38;' : ''}"
        >
            <span class="scada-mimic-node__icon" aria-hidden="true">${escapeHtml(device.icon)}</span>
            <div class="scada-mimic-node__body">
                <strong>${escapeHtml(device.name)}</strong>
                <span>${escapeHtml(status.label)} • ${escapeHtml(device.zone)}</span>
            </div>
            <span class="scada-mimic-node__dot" aria-hidden="true"></span>
        </article>
    `;
}

function renderShiftPanel() {
    const output = findDevice('packing-counter')?.primary.value || 0;
    const target = findDevice('packing-counter')?.metrics[0]?.value || 1;
    const energy = findDevice('energy-main')?.primary.value || 0;
    const steam = findDevice('boiler-house')?.metrics[0]?.value || 0;
    const outputProgress = clamp((output / target) * 100, 0, 100);

    return `
        <section class="scada-panel" aria-label="Shift summary">
            <div class="scada-panel__header">
                <div>
                    <p class="scada-demo__section-label">${escapeHtml(SHIFT_OPTIONS[state.shiftIndex])}</p>
                    <h2>Supervisor summary</h2>
                </div>
                <span class="scada-status-pill" style="--status-color: ${state.maintenanceDrill ? STATUS_META.warning.color : STATUS_META.running.color}">
                    ${state.maintenanceDrill ? 'Drill' : 'On plan'}
                </span>
            </div>
            <div class="scada-mini-grid">
                ${renderMiniPanel('Output progress', `${formatNumber(outputProgress, 0)}%`, outputProgress, STATUS_META.running.color)}
                ${renderMiniPanel('Energy demand', `${formatNumber(energy, 0)} kW`, clamp((energy / 920) * 100, 0, 100), STATUS_META.warning.color)}
                ${renderMiniPanel('Steam load', `${formatNumber(steam, 1)} t/h`, clamp((steam / 6.4) * 100, 0, 100), STATUS_META.running.color)}
                ${renderMiniPanel('Quality index', `${formatNumber(calculateQualityIndex(), 0)}%`, calculateQualityIndex(), STATUS_META.cyan?.color || '#22d3ee')}
            </div>
        </section>
    `;
}

function renderMiniPanel(label, value, progress, color) {
    return `
        <article class="scada-mini-panel">
            <span class="scada-mini-panel__label">${escapeHtml(label)}</span>
            <strong>${escapeHtml(value)}</strong>
            <div class="scada-progress" style="--progress-color: ${color}">
                <span style="--progress-value: ${progress}%"></span>
            </div>
        </article>
    `;
}

function renderEventPanel() {
    return `
        <section class="scada-panel" aria-label="Alarm and event feed">
            <div class="scada-panel__header">
                <div>
                    <p class="scada-demo__section-label">Alarm/event feed</p>
                    <h2>Recent plant messages</h2>
                </div>
                <span class="scada-status-pill ${activeAlarmCount() > 0 ? 'is-live' : ''}" style="--status-color: ${activeAlarmCount() > 0 ? STATUS_META.warning.color : STATUS_META.running.color}">
                    ${activeAlarmCount()} active
                </span>
            </div>
            <div class="scada-events">
                ${state.events.slice(0, 8).map(renderEvent).join('')}
            </div>
        </section>
    `;
}

function renderEvent(event) {
    const status = STATUS_META[event.status] || STATUS_META.running;

    return `
        <article class="scada-event" style="--event-color: ${status.color}">
            <span class="scada-event__marker" aria-hidden="true"></span>
            <div>
                <span class="scada-event__time">${escapeHtml(formatEventTime(event.createdAt))}</span>
                <strong>${escapeHtml(event.title)}</strong>
                <p>${escapeHtml(event.message)}</p>
            </div>
        </article>
    `;
}

function renderDeviceGrid() {
    return `
        <section class="scada-device-grid" aria-label="Device type cards">
            ${state.devices.map(renderDeviceCard).join('')}
        </section>
    `;
}

function renderDeviceCard(device) {
    const status = STATUS_META[device.status] || STATUS_META.idle;
    const isDimmed = state.selectedArea !== 'all' && state.selectedArea !== device.area;

    return `
        <article class="scada-device-card ${isDimmed ? 'is-dimmed' : ''}" style="--status-color: ${status.color}">
            <header class="scada-device-card__header">
                <div class="scada-device-card__identity">
                    <span class="scada-device-card__icon" aria-hidden="true">${escapeHtml(device.icon)}</span>
                    <div>
                        <p class="scada-device-card__label">${escapeHtml(device.type)} • ${escapeHtml(device.zone)}</p>
                        <h3>${escapeHtml(device.name)}</h3>
                    </div>
                </div>
                <span class="scada-status-pill ${status.live ? 'is-live' : ''}" style="--status-color: ${status.color}">${escapeHtml(status.label)}</span>
            </header>
            ${renderDeviceMetric(device.primary)}
            ${device.metrics.map(renderDeviceMetric).join('')}
            <div class="scada-trend">
                <span class="scada-trend__label">Live trend • ${escapeHtml(device.primary.label)}</span>
                ${renderSparkline(device.trend, status.color)}
            </div>
        </article>
    `;
}

function renderDeviceMetric(item) {
    return `
        <div class="scada-device-card__metric">
            <small>${escapeHtml(item.label)}</small>
            <strong>${escapeHtml(formatNumber(item.value, item.precision))} <small>${escapeHtml(item.unit)}</small></strong>
        </div>
    `;
}

function renderSparkline(values, color) {
    const finiteValues = values.filter((value) => Number.isFinite(value));
    const min = Math.min(...finiteValues);
    const max = Math.max(...finiteValues);
    const span = Math.max(max - min, 1);
    const points = finiteValues.map((value, index) => {
        const x = (index / Math.max(finiteValues.length - 1, 1)) * 100;
        const y = 50 - ((value - min) / span) * 42;

        return `${roundTo(x, 2)},${roundTo(y, 2)}`;
    });
    const areaPoints = [`0,54`, ...points, `100,54`].join(' ');

    return `
        <svg class="scada-sparkline" viewBox="0 0 100 54" preserveAspectRatio="none" role="img" aria-label="${escapeHtml('Trend line')}">
            <polygon class="scada-sparkline__area" points="${areaPoints}" style="--status-color: ${color}"></polygon>
            <polyline class="scada-sparkline__line" points="${points.join(' ')}" style="--status-color: ${color}"></polyline>
        </svg>
    `;
}

function renderFooter() {
    return `
        <footer class="scada-demo__footer">
            <span><strong>Demo mode:</strong> simulated in the browser; no telemetry, database, or device control is connected.</span>
            <span>Designed for factory walk-throughs: overview first, device detail second, alarms always visible.</span>
        </footer>
    `;
}

function calculateKpis() {
    const running = state.devices.filter((device) => device.status === 'running').length;
    const warning = state.devices.filter((device) => ['warning', 'fault'].includes(device.status)).length;
    const output = findDevice('packing-counter')?.primary.value || 0;
    const target = findDevice('packing-counter')?.metrics[0]?.value || 1;
    const demand = findDevice('energy-main')?.primary.value || 0;
    const compressor = findDevice('compressor-room')?.metrics[1]?.value || 0;
    const steam = findDevice('boiler-house')?.metrics[0]?.value || 0;
    const utilization = findDevice('stenter-01')?.metrics[1]?.value || 0;
    const quality = calculateQualityIndex();
    const oee = clamp((utilization * 0.52) + (quality * 0.28) + ((output / target) * 100 * 0.2), 0, 99);

    return [
        {
            label: 'Plant state',
            value: `${running}/${state.devices.length}`,
            caption: 'devices running',
            color: STATUS_META.running.color,
        },
        {
            label: 'Demo OEE',
            value: `${formatNumber(oee, 0)}%`,
            caption: 'availability × quality × performance',
            color: '#22d3ee',
        },
        {
            label: 'Shift output',
            value: `${formatNumber(output, 0)} m`,
            caption: `${formatNumber((output / target) * 100, 0)}% of target`,
            color: '#60a5fa',
        },
        {
            label: 'Energy now',
            value: `${formatNumber(demand + compressor, 0)} kW`,
            caption: 'main incomer plus compressed air load',
            color: STATUS_META.warning.color,
        },
        {
            label: 'Steam load',
            value: `${formatNumber(steam, 1)} t/h`,
            caption: 'boiler house to process lines',
            color: '#a78bfa',
        },
        {
            label: 'Alarms',
            value: `${warning}`,
            caption: state.maintenanceDrill ? 'maintenance drill active' : 'warning/fault devices',
            color: warning > 0 ? STATUS_META.fault.color : STATUS_META.running.color,
        },
    ];
}

function calculateQualityIndex() {
    const environment = findDevice('environment-qc')?.metrics[1]?.value || 90;
    const rejects = findDevice('packing-counter')?.metrics[1]?.value || 0;

    return clamp(environment - rejects * 0.38, 62, 99);
}

function activeAlarmCount() {
    return state.devices.filter((device) => ['warning', 'fault'].includes(device.status)).length;
}

function findDevice(id) {
    return state.devices.find((device) => device.id === id);
}

function pushEvent(status, title, message) {
    state.events = [makeEvent(new Date(), status, title, message), ...state.events].slice(0, 16);
}

function makeEvent(createdAt, status, title, message) {
    return {
        createdAt,
        status,
        title,
        message,
    };
}

function updateClockOnly() {
    const clock = root.querySelector('[data-scada-clock]');

    if (clock) {
        clock.textContent = formatClock(new Date());
    }
}

function formatClock(date) {
    return new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        day: '2-digit',
        month: 'short',
    }).format(date);
}

function formatEventTime(date) {
    return new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(date);
}

function formatNumber(value, precision = 0) {
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
        return '—';
    }

    return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: precision,
        maximumFractionDigits: precision,
    }).format(numericValue);
}

function randomBetween(min, max) {
    return Math.random() * (max - min) + min;
}

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

function roundTo(value, precision = 0) {
    const multiplier = 10 ** precision;

    return Math.round(value * multiplier) / multiplier;
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
