<x-filament-panels::page>
    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridstack@10.3.1/dist/gridstack.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridstack@10.3.1/dist/gridstack-extra.min.css">
    @endpush

    <style>
        .iot-dashboard-shell {
            display: grid;
            gap: 1rem;
        }

        .iot-dashboard-grid {
            min-height: 340px;
            padding-bottom: 1rem;
        }

        .grid-stack > .grid-stack-item {
            overflow: hidden;
        }

        .iot-widget-card {
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            overflow: hidden;
            background-color: var(--gray-50);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.06);
            display: flex;
            flex-direction: column;
        }

        :is(.dark, .dark *) .iot-widget-card {
            border-color: var(--gray-700);
            background-color: var(--gray-900);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .iot-widget-card header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        :is(.dark, .dark *) .iot-widget-card header {
            border-bottom-color: var(--gray-700);
        }

        .iot-widget-title {
            font-size: 0.95rem;
            line-height: 1.3rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
        }

        :is(.dark, .dark *) .iot-widget-title {
            color: var(--gray-100);
        }

        .iot-widget-meta {
            margin-top: 0.25rem;
            font-size: 0.74rem;
            line-height: 1.05rem;
            color: var(--gray-500);
        }

        :is(.dark, .dark *) .iot-widget-meta {
            color: var(--gray-400);
        }

        .iot-widget-flags {
            display: inline-flex;
            gap: 0.35rem;
            align-items: center;
        }

        .iot-widget-chart {
            flex: 1 1 0%;
            min-height: 0;
            width: 100%;
        }

        .iot-empty-state {
            padding: 1.35rem 1rem;
            border: 1px dashed var(--gray-300);
            border-radius: 0.75rem;
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        :is(.dark, .dark *) .iot-empty-state {
            border-color: var(--gray-600);
            color: var(--gray-400);
        }

        .grid-stack-placeholder > .placeholder-content {
            border-radius: 0.75rem;
            border: 1px dashed rgba(34, 211, 238, 0.45);
            background: rgba(2, 132, 199, 0.08);
        }

        .gs-24 > .grid-stack-item {
            width: calc(100% / 24);
        }

        @for ($column = 1; $column <= 24; $column++)
            .gs-24 > .grid-stack-item[gs-x="{{ $column }}"] {
                left: calc((100% / 24) * {{ $column }});
            }

            .gs-24 > .grid-stack-item[gs-w="{{ $column }}"] {
                width: calc((100% / 24) * {{ $column }});
            }
        @endfor
    </style>

    <div class="iot-dashboard-shell">
        @if (! $this->selectedDashboard)
            <x-filament::section
                heading="No Dashboard Selected"
                description="Open a dashboard from the Dashboards list to configure widgets and visualize telemetry."
            >
                <div class="iot-empty-state">
                    No dashboard was selected. Use <strong>Dashboards</strong> and open one from the table action.
                </div>

                <div class="mt-4">
                    <x-filament::button tag="a" href="{{ \App\Filament\Admin\Resources\IoTDashboards\IoTDashboardResource::getUrl() }}">
                        Open Dashboards
                    </x-filament::button>
                </div>
            </x-filament::section>
        @else
            @if ($this->selectedDashboard->widgets->isEmpty())
                <div class="iot-empty-state">
                    No widgets yet. Click <strong>Add Line Widget</strong> or <strong>Add Bar Widget</strong>, choose a topic, then choose the exact device and parameters.
                </div>
            @else
                @php(
                    $widgetLayouts = collect($this->widgetBootstrapPayload)
                        ->mapWithKeys(fn (array $widget): array => [(int) $widget['id'] => (array) ($widget['layout'] ?? [])])
                        ->all()
                )

                <div class="iot-dashboard-grid grid-stack" id="iot-dashboard-grid">
                    @foreach ($this->selectedDashboard->widgets as $widget)
                        @php($layout = $widgetLayouts[(int) $widget->id] ?? [])
                        @php($gridSpan = max(1, min(24, (int) data_get($layout, 'w', (int) data_get($widget->options, 'grid_columns', 1)))))
                        @php($gridHeight = max(2, min(12, (int) data_get($layout, 'h', (int) ceil(max(260, min(900, (int) data_get($widget->options, 'card_height_px', 360))) / 96)))))
                        @php($gridX = max(0, (int) data_get($layout, 'x', 0)))
                        @php($gridY = max(0, (int) data_get($layout, 'y', 0)))

                        <div
                            class="grid-stack-item"
                            gs-id="{{ $widget->id }}"
                            gs-x="{{ $gridX }}"
                            gs-y="{{ $gridY }}"
                            gs-w="{{ $gridSpan }}"
                            gs-h="{{ $gridHeight }}"
                        >
                            <article class="iot-widget-card grid-stack-item-content">
                                <header>
                                    <div>
                                        <h3 class="iot-widget-title">{{ $widget->title }}</h3>
                                        <p class="iot-widget-meta">
                                            {{ $widget->topic?->label ?? 'Unknown topic' }}
                                            @if ($widget->topic?->suffix)
                                                ({{ $widget->topic->suffix }})
                                            @endif
                                            ·
                                            {{ $widget->device?->name ?? 'Unknown device' }}
                                        </p>
                                    </div>

                                    <div class="iot-widget-flags">
                                        <x-filament::badge :color="$widget->use_websocket ? 'success' : 'gray'" size="sm">
                                            WS
                                        </x-filament::badge>
                                        <x-filament::badge :color="$widget->use_polling ? 'info' : 'gray'" size="sm">
                                            Poll
                                        </x-filament::badge>
                                        {{ ($this->editWidgetAction)(['widget' => $widget->id]) }}
                                        {{ ($this->deleteWidgetAction)(['widget' => $widget->id]) }}
                                    </div>
                                </header>

                                <div class="iot-widget-chart" id="iot-widget-chart-{{ $widget->id }}"></div>
                            </article>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/gridstack@10.3.1/dist/gridstack-all.js"></script>
        <script>
            window.iotDashboardWidgets = @js($this->widgetBootstrapPayload);
            window.iotDashboardRealtimeConfig = {
                key: @js(config('broadcasting.connections.reverb.key')),
                host: @js(config('broadcasting.connections.reverb.options.host')),
                port: @js(config('broadcasting.connections.reverb.options.port')),
                scheme: @js(config('broadcasting.connections.reverb.options.scheme')),
            };
        </script>
    @endpush
</x-filament-panels::page>
