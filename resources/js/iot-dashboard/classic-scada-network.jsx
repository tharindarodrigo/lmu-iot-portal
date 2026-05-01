import '@xyflow/react/dist/style.css';

import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
    Background,
    Controls,
    Handle,
    MarkerType,
    MiniMap,
    Panel,
    Position,
    ReactFlow,
    useNodesState,
} from '@xyflow/react';

const STATUS = Object.freeze({
    running: { label: 'RUN', color: '#27d86f' },
    stopped: { label: 'STOP', color: '#7f93a8' },
    warning: { label: 'WARN', color: '#ffce33' },
    critical: { label: 'TRIP', color: '#ff4545' },
    maintenance: { label: 'MAINT', color: '#b59cff' },
});

const INITIAL_TELEMETRY = Object.freeze({
    rawLevel: 72,
    feedFlow: 148,
    pumpSpeed: 2860,
    boilerPressure: 9.2,
    steamFlow: 4.8,
    stenterTemp: 182,
    stenterSpeed: 61,
    compressorPressure: 6.9,
    airFlow: 384,
    condensateLevel: 42,
    gatewayPackets: 1184,
    plcScan: 18,
    energyDemand: 726,
});

const BASE_NODES = Object.freeze([
    { id: 'raw-tank', type: 'scadaNode', position: { x: 40, y: 140 }, data: { kind: 'tank', title: 'TK-101', label: 'Raw Water Tank', area: 'Water' } },
    { id: 'inlet-valve', type: 'scadaNode', position: { x: 280, y: 164 }, data: { kind: 'valve', title: 'XV-102', label: 'Inlet Valve', area: 'Water' } },
    { id: 'feed-pump', type: 'scadaNode', position: { x: 500, y: 148 }, data: { kind: 'pump', title: 'P-201', label: 'Feed Pump', area: 'Utility' } },
    { id: 'boiler', type: 'scadaNode', position: { x: 760, y: 114 }, data: { kind: 'heat-exchanger', title: 'BLR-301', label: 'Boiler / Heat Exchanger', area: 'Steam' } },
    { id: 'steam-meter', type: 'scadaNode', position: { x: 1050, y: 130 }, data: { kind: 'meter', title: 'FT-302', label: 'Steam Flow Meter', area: 'Steam' } },
    { id: 'stenter', type: 'scadaNode', position: { x: 1320, y: 112 }, data: { kind: 'machine', title: 'ST-401', label: 'Stenter Dryer', area: 'Finishing' } },
    { id: 'condensate-tank', type: 'scadaNode', position: { x: 1050, y: 398 }, data: { kind: 'tank', title: 'TK-501', label: 'Condensate Return', area: 'Steam' } },
    { id: 'compressor', type: 'scadaNode', position: { x: 760, y: 384 }, data: { kind: 'pump', title: 'AC-701', label: 'Air Compressor', area: 'Air' } },
    { id: 'air-header', type: 'scadaNode', position: { x: 1320, y: 384 }, data: { kind: 'meter', title: 'PT-702', label: 'Compressed Air Header', area: 'Air' } },
    { id: 'plc', type: 'scadaNode', position: { x: 310, y: 438 }, data: { kind: 'rack', title: 'PLC-01', label: 'Line PLC Rack', area: 'Control' } },
    { id: 'gateway', type: 'scadaNode', position: { x: 520, y: 438 }, data: { kind: 'gateway', title: 'GW-EDGE', label: 'Modbus / OPC-UA Gateway', area: 'Control' } },
    { id: 'energy', type: 'scadaNode', position: { x: 40, y: 430 }, data: { kind: 'meter', title: 'EM-901', label: 'Main Energy Meter', area: 'Electrical' } },
]);

const BASE_EDGES = Object.freeze([
    edge('raw-tank', 'inlet-valve', 'water', '#42b8ff', 'Water supply'),
    edge('inlet-valve', 'feed-pump', 'water', '#42b8ff', '148 m³/h'),
    edge('feed-pump', 'boiler', 'water', '#42b8ff', 'feed water'),
    edge('boiler', 'steam-meter', 'steam', '#ffce33', 'steam'),
    edge('steam-meter', 'stenter', 'steam', '#ffce33', '4.8 t/h'),
    edge('stenter', 'condensate-tank', 'condensate', '#36e2e2', 'condensate return'),
    edge('compressor', 'air-header', 'air', '#b59cff', 'compressed air'),
    edge('air-header', 'stenter', 'air', '#b59cff', '6.9 bar'),
    edge('energy', 'boiler', 'power', '#27d86f', 'power'),
    edge('energy', 'compressor', 'power', '#27d86f', 'power'),
    edge('plc', 'raw-tank', 'telemetry', '#9bb8d4', 'AI level'),
    edge('plc', 'feed-pump', 'telemetry', '#9bb8d4', 'VFD status'),
    edge('plc', 'stenter', 'telemetry', '#9bb8d4', 'line status'),
    edge('gateway', 'plc', 'telemetry', '#36e2e2', 'Modbus TCP'),
    edge('gateway', 'energy', 'telemetry', '#36e2e2', 'meter bus'),
]);

const NODE_TYPES = { scadaNode: ClassicScadaNode };

function edge(source, target, networkType, color, label) {
    return {
        id: `${source}-${target}`,
        source,
        target,
        type: 'smoothstep',
        animated: networkType !== 'telemetry',
        label,
        markerEnd: { type: MarkerType.ArrowClosed, color },
        style: {
            stroke: color,
            '--edge-glow': color,
        },
        data: { networkType },
    };
}

function ClassicScadaNetworkDashboard() {
    const [telemetry, setTelemetry] = useState(INITIAL_TELEMETRY);
    const [nodes, setNodes, onNodesChange] = useNodesState(buildNodes(INITIAL_TELEMETRY, false));
    const [isPaused, setIsPaused] = useState(false);
    const [showTelemetryLinks, setShowTelemetryLinks] = useState(true);
    const [isAlarmDrill, setIsAlarmDrill] = useState(false);
    const [hasCustomLayout, setHasCustomLayout] = useState(false);
    const [events, setEvents] = useState(() => initialEvents());

    useEffect(() => {
        const interval = window.setInterval(() => {
            if (isPaused) {
                return;
            }

            setTelemetry((current) => nextTelemetry(current, isAlarmDrill));
            setEvents((current) => rotateEvents(current, isAlarmDrill));
        }, 1500);

        return () => window.clearInterval(interval);
    }, [isAlarmDrill, isPaused]);

    useEffect(() => {
        setNodes((currentNodes) => syncNodesWithTelemetry(currentNodes, telemetry, isAlarmDrill));
    }, [isAlarmDrill, setNodes, telemetry]);

    const edges = useMemo(() => buildEdges(telemetry, showTelemetryLinks, isAlarmDrill), [isAlarmDrill, showTelemetryLinks, telemetry]);
    const summaries = useMemo(() => buildSummaries(telemetry, isAlarmDrill), [isAlarmDrill, telemetry]);

    const handleNodesChange = useCallback((changes) => {
        if (changes.some((change) => change.type === 'position' && (change.dragging || change.position))) {
            setHasCustomLayout(true);
        }

        onNodesChange(changes);
    }, [onNodesChange]);

    const resetLayout = useCallback(() => {
        setNodes(buildNodes(telemetry, isAlarmDrill));
        setHasCustomLayout(false);
        setEvents((events) => [
            makeEvent('running', 'SCADA layout reset', 'All draggable components returned to the default process layout.'),
            ...events,
        ].slice(0, 12));
    }, [isAlarmDrill, setNodes, telemetry]);

    const toggleAlarmDrill = useCallback(() => {
        setIsAlarmDrill((current) => {
            const next = !current;
            setEvents((events) => [
                makeEvent(next ? 'critical' : 'running', next ? 'Alarm drill armed' : 'Alarm drill cleared', next ? 'Boiler pressure and compressor pressure moved outside normal operating bands.' : 'All demo equipment returned to normal simulated operating bands.'),
                ...events,
            ].slice(0, 12));

            return next;
        });
    }, []);

    return (
        <div className="classic-scada__frame">
            <header className="classic-scada__header">
                <div className="classic-scada__title">
                    <h1>Classic SCADA Component Network</h1>
                    <span className="classic-scada__tag" style={{ '--tag-color': isAlarmDrill ? STATUS.critical.color : STATUS.running.color }}>
                        {isAlarmDrill ? 'Alarm drill' : 'Normal operation'}
                    </span>
                    <span className="classic-scada__tag" style={{ '--tag-color': STATUS.blue?.color ?? '#42b8ff' }}>
                        Drag components
                    </span>
                </div>

                <div className="classic-scada__toolbar" aria-label="Classic SCADA controls">
                    <button
                        className={`classic-scada__button ${hasCustomLayout ? 'is-active' : ''}`}
                        type="button"
                        onClick={resetLayout}
                    >
                        Reset layout
                    </button>
                    <button
                        className={`classic-scada__button ${isPaused ? '' : 'is-active'}`}
                        type="button"
                        onClick={() => setIsPaused((current) => !current)}
                    >
                        {isPaused ? 'Resume scan' : 'Pause scan'}
                    </button>
                    <button
                        className={`classic-scada__button ${showTelemetryLinks ? 'is-active' : ''}`}
                        type="button"
                        onClick={() => setShowTelemetryLinks((current) => !current)}
                    >
                        {showTelemetryLinks ? 'Hide telemetry' : 'Show telemetry'}
                    </button>
                    <button
                        className={`classic-scada__button ${isAlarmDrill ? 'is-danger' : ''}`}
                        type="button"
                        onClick={toggleAlarmDrill}
                    >
                        {isAlarmDrill ? 'Clear alarm drill' : 'Trigger alarm drill'}
                    </button>
                </div>
            </header>

            <section className="classic-scada__content">
                <section className="classic-scada__canvas-panel" aria-label="Classic SCADA network canvas">
                    <ReactFlow
                        nodes={nodes}
                        edges={edges}
                        nodeTypes={NODE_TYPES}
                        onNodesChange={handleNodesChange}
                        defaultEdgeOptions={{ type: 'smoothstep' }}
                        fitView
                        fitViewOptions={{ padding: 0.16 }}
                        minZoom={0.38}
                        maxZoom={1.45}
                        nodesDraggable
                        nodesConnectable={false}
                        snapToGrid
                        snapGrid={[16, 16]}
                        proOptions={{ hideAttribution: true }}
                        colorMode="dark"
                    >
                        <Panel className="classic-scada__flow-panel" position="top-left">
                            Drag any SCADA symbol to rearrange • Water → Steam → Stenter • Air utility • PLC telemetry
                        </Panel>
                        <MiniMap
                            pannable
                            zoomable
                            maskColor="rgba(2, 6, 23, 0.52)"
                            nodeColor={(node) => node?.data?.statusColor ?? '#42b8ff'}
                        />
                        <Controls />
                        <Background gap={32} size={1} color="#31506f" bgColor="#08172a" />
                    </ReactFlow>
                </section>

                <aside className="classic-scada__side-panel" aria-label="SCADA alarm and scan summary">
                    <h2 className="classic-scada__panel-heading">Runtime Summary</h2>
                    <div className="classic-scada__summary-grid">
                        {summaries.map((summary) => (
                            <article className="classic-scada__summary-tile" key={summary.label}>
                                <span>{summary.label}</span>
                                <strong>{summary.value}</strong>
                            </article>
                        ))}
                    </div>

                    <h2 className="classic-scada__panel-heading">Alarm/Event Stack</h2>
                    <div className="classic-scada__alarm-list">
                        {events.map((event, index) => {
                            const status = STATUS[event.status] ?? STATUS.running;

                            return (
                                <article className="classic-scada__alarm" key={`${event.title}-${index}`} style={{ '--alarm-color': status.color }}>
                                    <span className="classic-scada__alarm-time">{event.time}</span>
                                    <strong>{event.title}</strong>
                                    <p>{event.message}</p>
                                </article>
                            );
                        })}
                    </div>
                </aside>
            </section>

            <footer className="classic-scada__footer">
                <div className="classic-scada__legend">
                    <span className="classic-scada__legend-item" style={{ '--legend-color': '#42b8ff' }}>Water</span>
                    <span className="classic-scada__legend-item" style={{ '--legend-color': '#ffce33' }}>Steam</span>
                    <span className="classic-scada__legend-item" style={{ '--legend-color': '#b59cff' }}>Compressed air</span>
                    <span className="classic-scada__legend-item" style={{ '--legend-color': '#36e2e2' }}>Telemetry</span>
                    <span className="classic-scada__legend-item" style={{ '--legend-color': '#ff4545' }}>Alarm</span>
                </div>
                <span>Demo only: no PLC, RTU, gateway, or device command path is connected.</span>
            </footer>
        </div>
    );
}

function ClassicScadaNode({ data }) {
    const status = STATUS[data.status] ?? STATUS.running;

    return (
        <article className="classic-scada__node" style={{ '--status-color': status.color }}>
            <Handle type="target" position={Position.Left} />
            <Handle type="source" position={Position.Right} />
            <Handle type="target" position={Position.Top} id="top-target" />
            <Handle type="source" position={Position.Bottom} id="bottom-source" />

            <header className="classic-scada__node-header">
                <strong className="classic-scada__node-title">{data.title}</strong>
                <span className="classic-scada__node-status" title={status.label} />
            </header>

            <div className="classic-scada__node-body">
                <span className="classic-scada__node-label">{data.label}</span>
                <ScadaSymbol data={data} />
                <div className="classic-scada__node-kv">
                    {data.values.map((item) => (
                        <React.Fragment key={item.label}>
                            <span>{item.label}</span>
                            <strong>{item.value}</strong>
                        </React.Fragment>
                    ))}
                </div>
            </div>
        </article>
    );
}

function ScadaSymbol({ data }) {
    if (data.kind === 'tank') {
        return (
            <div className="classic-scada__symbol">
                <div className="classic-scada__tank">
                    <span className="classic-scada__tank-fill" style={{ '--fill-level': `${data.fillLevel ?? 50}%` }} />
                </div>
            </div>
        );
    }

    if (data.kind === 'pump') {
        return <div className="classic-scada__symbol"><div className="classic-scada__pump" /></div>;
    }

    if (data.kind === 'valve') {
        return <div className="classic-scada__symbol"><div className="classic-scada__valve"><span className="classic-scada__valve-stem" /></div></div>;
    }

    if (data.kind === 'heat-exchanger') {
        return <div className="classic-scada__symbol"><div className="classic-scada__heat-exchanger" /></div>;
    }

    if (data.kind === 'machine') {
        return <div className="classic-scada__symbol"><div className="classic-scada__machine"><span /><span /><span /><span /><span /><span /><span /><span /></div></div>;
    }

    if (data.kind === 'rack') {
        return <div className="classic-scada__symbol"><div className="classic-scada__rack"><span /><span /><span /><span /></div></div>;
    }

    if (data.kind === 'gateway') {
        return <div className="classic-scada__symbol"><div className="classic-scada__gateway">↔</div></div>;
    }

    return (
        <div className="classic-scada__symbol">
            <div className="classic-scada__meter" style={{ '--meter-value': `${data.meterValue ?? 60}%` }} />
        </div>
    );
}

function buildNodes(telemetry, isAlarmDrill) {
    const dataById = {
        'raw-tank': {
            status: statusFromBand(telemetry.rawLevel, 22, 86),
            fillLevel: telemetry.rawLevel,
            values: [
                { label: 'Level', value: `${formatNumber(telemetry.rawLevel, 0)} %` },
                { label: 'Temp', value: '28 °C' },
            ],
        },
        'inlet-valve': {
            status: isAlarmDrill ? 'warning' : 'running',
            values: [
                { label: 'Position', value: isAlarmDrill ? '62 %' : 'OPEN' },
                { label: 'Mode', value: 'AUTO' },
            ],
        },
        'feed-pump': {
            status: isAlarmDrill ? 'warning' : 'running',
            values: [
                { label: 'Flow', value: `${formatNumber(telemetry.feedFlow, 0)} m³/h` },
                { label: 'Speed', value: `${formatNumber(telemetry.pumpSpeed, 0)} rpm` },
            ],
        },
        boiler: {
            status: isAlarmDrill || telemetry.boilerPressure > 10.4 ? 'critical' : 'running',
            values: [
                { label: 'Pressure', value: `${formatNumber(telemetry.boilerPressure, 1)} bar` },
                { label: 'Demand', value: `${formatNumber(telemetry.steamFlow, 1)} t/h` },
            ],
        },
        'steam-meter': {
            status: isAlarmDrill ? 'warning' : 'running',
            meterValue: clamp((telemetry.steamFlow / 7) * 100, 0, 100),
            values: [
                { label: 'Flow', value: `${formatNumber(telemetry.steamFlow, 1)} t/h` },
                { label: 'Total', value: '1,137 t' },
            ],
        },
        stenter: {
            status: isAlarmDrill ? 'warning' : 'running',
            values: [
                { label: 'Temp', value: `${formatNumber(telemetry.stenterTemp, 0)} °C` },
                { label: 'Speed', value: `${formatNumber(telemetry.stenterSpeed, 1)} m/min` },
            ],
        },
        'condensate-tank': {
            status: statusFromBand(telemetry.condensateLevel, 18, 82),
            fillLevel: telemetry.condensateLevel,
            values: [
                { label: 'Level', value: `${formatNumber(telemetry.condensateLevel, 0)} %` },
                { label: 'Return', value: 'ONLINE' },
            ],
        },
        compressor: {
            status: isAlarmDrill || telemetry.compressorPressure < 6.2 ? 'warning' : 'running',
            values: [
                { label: 'Pressure', value: `${formatNumber(telemetry.compressorPressure, 1)} bar` },
                { label: 'Flow', value: `${formatNumber(telemetry.airFlow, 0)} Nm³/h` },
            ],
        },
        'air-header': {
            status: isAlarmDrill ? 'warning' : 'running',
            meterValue: clamp((telemetry.compressorPressure / 8.5) * 100, 0, 100),
            values: [
                { label: 'Header', value: `${formatNumber(telemetry.compressorPressure, 1)} bar` },
                { label: 'Demand', value: `${formatNumber(telemetry.airFlow, 0)} Nm³/h` },
            ],
        },
        plc: {
            status: isAlarmDrill ? 'warning' : 'running',
            values: [
                { label: 'Scan', value: `${formatNumber(telemetry.plcScan, 0)} ms` },
                { label: 'I/O', value: '128 pts' },
            ],
        },
        gateway: {
            status: 'running',
            values: [
                { label: 'Packets', value: `${formatNumber(telemetry.gatewayPackets, 0)}/m` },
                { label: 'Link', value: 'TLS OK' },
            ],
        },
        energy: {
            status: isAlarmDrill ? 'warning' : 'running',
            meterValue: clamp((telemetry.energyDemand / 980) * 100, 0, 100),
            values: [
                { label: 'Demand', value: `${formatNumber(telemetry.energyDemand, 0)} kW` },
                { label: 'PF', value: '0.96' },
            ],
        },
    };

    return BASE_NODES.map((node) => {
        const mergedData = {
            ...node.data,
            ...dataById[node.id],
        };
        const status = STATUS[mergedData.status] ?? STATUS.running;

        return {
            ...node,
            data: {
                ...mergedData,
                statusColor: status.color,
            },
        };
    });
}

function syncNodesWithTelemetry(currentNodes, telemetry, isAlarmDrill) {
    const currentById = new Map(currentNodes.map((node) => [node.id, node]));

    return buildNodes(telemetry, isAlarmDrill).map((nextNode) => {
        const currentNode = currentById.get(nextNode.id);

        if (!currentNode) {
            return nextNode;
        }

        return {
            ...nextNode,
            dragging: currentNode.dragging,
            height: currentNode.height,
            measured: currentNode.measured,
            position: currentNode.position,
            selected: currentNode.selected,
            width: currentNode.width,
            zIndex: currentNode.zIndex,
        };
    });
}

function buildEdges(telemetry, showTelemetryLinks, isAlarmDrill) {
    return BASE_EDGES
        .filter((edge) => showTelemetryLinks || edge.data.networkType !== 'telemetry')
        .map((edge) => {
            const nextEdge = { ...edge };

            if (edge.id === 'steam-meter-stenter') {
                nextEdge.label = `${formatNumber(telemetry.steamFlow, 1)} t/h`;
            }

            if (edge.id === 'air-header-stenter') {
                nextEdge.label = `${formatNumber(telemetry.compressorPressure, 1)} bar`;
            }

            if (edge.id === 'inlet-valve-feed-pump') {
                nextEdge.label = `${formatNumber(telemetry.feedFlow, 0)} m³/h`;
            }

            if (isAlarmDrill && ['boiler-steam-meter', 'steam-meter-stenter', 'compressor-air-header', 'air-header-stenter'].includes(edge.id)) {
                return {
                    ...nextEdge,
                    animated: true,
                    markerEnd: { type: MarkerType.ArrowClosed, color: STATUS.critical.color },
                    style: {
                        ...nextEdge.style,
                        stroke: STATUS.critical.color,
                        '--edge-glow': STATUS.critical.color,
                    },
                };
            }

            return nextEdge;
        });
}

function buildSummaries(telemetry, isAlarmDrill) {
    return [
        { label: 'PLC scan', value: `${formatNumber(telemetry.plcScan, 0)} ms` },
        { label: 'Steam flow', value: `${formatNumber(telemetry.steamFlow, 1)} t/h` },
        { label: 'Air header', value: `${formatNumber(telemetry.compressorPressure, 1)} bar` },
        { label: 'Energy', value: `${formatNumber(telemetry.energyDemand, 0)} kW` },
        { label: 'Gateway rx', value: `${formatNumber(telemetry.gatewayPackets, 0)}/m` },
        { label: 'Alarm state', value: isAlarmDrill ? 'TRIP' : 'NORMAL' },
    ];
}

function nextTelemetry(current, isAlarmDrill) {
    return {
        rawLevel: clamp(current.rawLevel + randomBetween(-1.1, 1.6), 52, 84),
        feedFlow: clamp(current.feedFlow + randomBetween(-4.6, 5.2), 112, 172),
        pumpSpeed: clamp(current.pumpSpeed + randomBetween(-35, 40), 2420, 2980),
        boilerPressure: clamp(current.boilerPressure + randomBetween(-0.12, 0.18) + (isAlarmDrill ? 0.18 : -0.02), 8.1, 11.3),
        steamFlow: clamp(current.steamFlow + randomBetween(-0.12, 0.16), 3.6, 6.4),
        stenterTemp: clamp(current.stenterTemp + randomBetween(-1.4, 1.8), 168, 202),
        stenterSpeed: clamp(current.stenterSpeed + randomBetween(-1.2, 1.1), 48, 72),
        compressorPressure: clamp(current.compressorPressure + randomBetween(-0.16, 0.11) - (isAlarmDrill ? 0.12 : 0), 5.6, 7.8),
        airFlow: clamp(current.airFlow + randomBetween(-14, 16), 310, 442),
        condensateLevel: clamp(current.condensateLevel + randomBetween(-1.4, 1.2), 28, 74),
        gatewayPackets: clamp(current.gatewayPackets + randomBetween(-28, 42), 980, 1360),
        plcScan: clamp(current.plcScan + randomBetween(-1.2, 1.4), 14, 28),
        energyDemand: clamp(current.energyDemand + randomBetween(-18, 24) + (isAlarmDrill ? 6 : 0), 620, 910),
    };
}

function rotateEvents(events, isAlarmDrill) {
    if (Math.random() < 0.55) {
        return events;
    }

    const options = isAlarmDrill
        ? [
            ['critical', 'BLR-301 high pressure', 'Steam pressure exceeded the simulated trip threshold.'],
            ['warning', 'AC-701 low header pressure', 'Compressed air header is below demo operating setpoint.'],
            ['warning', 'XV-102 position mismatch', 'Valve feedback differs from PLC command by more than 10%.'],
        ]
        : [
            ['running', 'P-201 flow stable', 'Feed water flow returned to normal range.'],
            ['running', 'GW-EDGE link healthy', 'Gateway heartbeat and Modbus polling remain online.'],
            ['running', 'ST-401 recipe temperature stable', 'Stenter chamber temperature remains inside tolerance.'],
            ['warning', 'TK-501 condensate watch', 'Condensate return level is trending downward.'],
        ];

    const [status, title, message] = options[Math.floor(Math.random() * options.length)];

    return [makeEvent(status, title, message), ...events].slice(0, 12);
}

function initialEvents() {
    return [
        makeEvent('running', 'SCADA network initialized', 'Classic component network loaded with simulated PLC, gateway, utilities, and process nodes.'),
        makeEvent('running', 'Modbus gateway online', 'GW-EDGE is polling PLC-01 and publishing mock telemetry.'),
        makeEvent('warning', 'Condensate return watch', 'Tank level is low but still inside the demo operating band.'),
        makeEvent('running', 'Stenter steam demand normal', 'ST-401 is receiving stable steam flow and compressed air.'),
    ];
}

function makeEvent(status, title, message) {
    return {
        status,
        title,
        message,
        time: new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }).format(new Date()),
    };
}

function statusFromBand(value, low, high) {
    if (value < low || value > high) {
        return 'critical';
    }

    if (value < low + 8 || value > high - 8) {
        return 'warning';
    }

    return 'running';
}

function formatNumber(value, precision = 0) {
    return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: precision,
        maximumFractionDigits: precision,
    }).format(Number(value));
}

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

function randomBetween(min, max) {
    return Math.random() * (max - min) + min;
}

function mountClassicScadaNetwork(container) {
    if (container.dataset.mounted === '1') {
        return;
    }

    container.dataset.mounted = '1';

    createRoot(container).render(<ClassicScadaNetworkDashboard />);
}

function bootstrapClassicScadaNetwork() {
    document.querySelectorAll('[data-classic-scada-network]').forEach((container) => mountClassicScadaNetwork(container));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapClassicScadaNetwork);
} else {
    bootstrapClassicScadaNetwork();
}

document.addEventListener('livewire:navigated', bootstrapClassicScadaNetwork);
