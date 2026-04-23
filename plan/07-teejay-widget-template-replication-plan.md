# Teejay Legacy Widget Template Replication Plan

## Purpose

This plan inventories the legacy Teejay widget templates from the local `../iot-demo` application and maps them to the current dashboard capability in `iot-portal`.

The inventory was pulled from the legacy local database via `php artisan tinker` against the `Teejay` organization (`organizations.id = 11`).

This plan intentionally focuses on legacy `dashboard_templates`, because those records contain the Grafana panel JSON, the custom HTML Graphics panel markup, and the Flux queries that drive the widgets. Legacy `device_type_reports` also exist, but they are a separate report/query surface and are not the widget templates requested here.

## What the current app already has

The current `iot-portal` widget surface is:

- `line_chart`
- `bar_chart`
- `gauge_chart`
- `status_summary`
- `state_card`
- `state_timeline`
- `threshold_status_card`
- `threshold_status_grid`

Concrete reuse examples already exist in the repo:

- `database/seeders/WitcoDashboardSeeder.php` uses `StateCard` and `StateTimeline`
- `database/seeders/MiracleDomeDashboardSeeder.php` uses `StatusSummary`, `LineChart`, and `BarChart`
- `database/seeders/TextripDashboardSeeder.php` uses `StatusSummary` and `BarChart`
- `database/seeders/SriLankanDashboardSeeder.php` uses `ThresholdStatusCard` and `LineChart`

## Current architectural constraint that matters

`iot_dashboard_widgets` in the new system currently binds one widget to:

- one `device_id`
- one `schema_version_topic_id`

That is enough for most current widgets, but it is not enough for several Teejay legacy templates, because some of them read from:

- multiple devices at once (`{{deviceIds}}`)
- multiple named scopes such as `{{status.*}}`, `{{energy.*}}`, and `{{length.*}}`

This means Teejay replication is not only a UI problem; it also needs a richer widget source-binding model.

There is also a likely Stenter-specific modeling wrinkle to account for: the legacy Stenter setup appears to use a virtual device abstraction that is composed from two or more underlying non-virtual devices for widgeting purposes. The repeated legacy template scopes (`status.*`, `length.*`, and sometimes `energy.*`) strongly suggest that Stenter should not be treated as a normal one-device-to-one-widget mapping in the new system. This should be tracked as a separate investigation and planning item before implementation.

## Summary of the Teejay legacy inventory

### Directly reusable with current widget types

These can be struck off with little or no new widget development:

- `#16 Hourly Energy (Bar chart)` → `BarChart`
- `#17 Water consumption (Bar chart)` → `BarChart`
- `#18 Water Flow rate` → `LineChart`
- `#23 Status - ON/OFF widget` → `StateCard`
- `#24 Open/Close - Doors` → `StateCard`
- `#25 Status - History` → `StateTimeline`
- `#30 Temperature - status` → `StatusSummary`
- `#31 Pressure - history` → `LineChart`
- `#32 Preassure` → `StatusSummary`
- `#33 Temperature - history` → `LineChart`
- `#39 Tank Level` → `LineChart`
- `#40 Trip/Run - Status` → `StateCard`

### Reuse by extending existing widgets

These do not need brand-new widget families, but the existing widgets need richer data-source support:

- `#7 Hourly Energy Consumption` → extend `BarChart` for multi-device aggregate inputs
- `#11 Stentner - current` → extend `LineChart` for multi-source named scopes
- `#14 Stenter - current` → extend `LineChart` for multi-source named scopes
- `#15 Stenter - voltage` → extend `LineChart` for multi-source named scopes

### New custom widget families required

These are the legacy HTML Graphics widgets that cannot be represented cleanly with the current typed widgets:

- `#8 Energy widget`
- `#9 Water widget`
- `#10 Stentner widget`
- `#12 Compressor utilisation widget`
- `#19 Steam flow meter status`
- `#20 Water Flow rate and volume (Status)`
- `#21 Stentner widget (Using length only)`
- `#22 Steam flow meter and consumption`
- `#27 Stentner widget (7.30): v1`
- `#28 Stenter (6:00)`
- `#34 Steam flow meter status - v2`
- `#36 Steam flow meter status with history`
- `#37 Preassure with history`
- `#38 Level Status`

### Teejay device types with no legacy widget templates attached

These currently have no Teejay `dashboard_templates` attached in legacy:

- `Fabric Length`
- `Fabric Length(Short)`
- `IMoni Hub`
- `IMoni Lite`

## Legacy widget inventory by device type

### AC Energy Mate

- `#7 Hourly Energy Consumption`
  - Legacy panel type: `barchart`
  - Query scope: `{{hubId}}`, `{{deviceIds}}`
  - Legacy behavior: hourly aggregate consumption across multiple energy devices under one hub context
  - New-system path: extend `BarChart` to support multi-device aggregate sources

- `#8 Energy widget`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{hubId}}`, `{{virtualDeviceId}}`
  - Legacy behavior: latest energy snapshot, start-of-day/month/shift baselines, plus recent voltage data
  - New-system path: new custom `metric_summary_card` family, energy variant

- `#11 Stentner - current`
  - Legacy panel type: `timeseries`
  - Query scope: `{{status.hub_id}}`, `{{status.virtual_device_id}}`, `{{energy.hub_id}}`, `{{energy.virtual_device_id}}`
  - Legacy behavior: cross-device overlay of status and current series
  - New-system path: extend `LineChart` for named multi-source inputs
  - Note: the legacy name does not match the device type; treat as suspicious until validated

- `#12 Compressor utilisation widget`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{hubId}}`, `{{virtualDeviceId}}`
  - Legacy behavior: derives ON/OFF state from phase currents, then computes utilization percentages by day/shift
  - New-system path: new custom `utilization_summary_card` family, compressor variant

- `#16 Hourly Energy (Bar chart)`
  - Legacy panel type: `barchart`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: hourly delta of `TotalEnergy`
  - New-system path: direct `BarChart`

### Modbus

- `#9 Water widget`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{hubId}}`, `{{virtualDeviceId}}`
  - Legacy behavior: latest water values with shift/day/month counter baselines
  - New-system path: new custom `metric_summary_card` family, water variant

### Water Flow and Volume

- `#17 Water consumption (Bar chart)`
  - Legacy panel type: `barchart`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: hourly delta of cumulative `volume`
  - New-system path: direct `BarChart`

- `#18 Water Flow rate`
  - Legacy panel type: `timeseries`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: flow trend over the selected range
  - New-system path: direct `LineChart`

- `#20 Water Flow rate and volume (Status)`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: latest flow/volume plus shift, daily, and monthly consumption deltas
  - New-system path: new custom `metric_summary_card` family, water variant with period deltas

### Stenter

> **Planning note:** Treat Stenter as a special-case legacy device type. It appears to behave like a virtual device assembled from multiple physical/non-virtual telemetry sources, likely to support composite production and utilization widgets. Replication should therefore include a separate discovery track to identify the source devices, how the virtual grouping is defined in legacy, and whether the new system should preserve a virtual-device abstraction or model the same relationship as named widget sources.

- `#10 Stentner widget`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{length.hub_id}}`, `{{length.virtual_device_id}}`, `{{status.hub_id}}`, `{{status.virtual_device_id}}`
  - Legacy behavior: combines production length metrics with utilization/status context
  - New-system path: new custom `utilization_summary_card` family with named multi-source inputs

- `#14 Stenter - current`
  - Legacy panel type: `timeseries`
  - Query scope: `{{status.hub_id}}`, `{{status.virtual_device_id}}`, `{{energy.hub_id}}`, `{{energy.virtual_device_id}}`
  - Legacy behavior: overlays status and phase-current signals from separate sources
  - New-system path: extend `LineChart` for named multi-source inputs

- `#15 Stenter - voltage`
  - Legacy panel type: `timeseries`
  - Query scope: `{{status.hub_id}}`, `{{status.virtual_device_id}}`, `{{energy.hub_id}}`, `{{energy.virtual_device_id}}`
  - Legacy behavior: overlays status and phase-voltage signals from separate sources
  - New-system path: extend `LineChart` for named multi-source inputs

- `#21 Stentner widget (Using length only)`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{length.hub_id}}`, `{{length.virtual_device_id}}`, `{{status.hub_id}}`, `{{status.virtual_device_id}}`
  - Legacy behavior: production counters with utilization breakdowns using a 06:00-based shift calendar
  - New-system path: new custom `utilization_summary_card` family, stenter variant

- `#27 Stentner widget (7.30): v1`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{length.virtual_device_id}}`, `{{status.virtual_device_id}}`
  - Legacy behavior: same stenter KPI card but with a 07:30 / 19:30 shift definition
  - New-system path: new custom `utilization_summary_card` family with configurable shift calendar

- `#28 Stenter (6:00)`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{length.virtual_device_id}}`, `{{status.virtual_device_id}}`
  - Legacy behavior: same stenter KPI card but with three 8-hour shifts anchored at 06:00
  - New-system path: new custom `utilization_summary_card` family with configurable shift calendar

### Steam meter

- `#19 Steam flow meter status`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: latest flow and total reading, including legacy multi-register total reconstruction
  - New-system path: new custom `metric_summary_card` family, steam variant

- `#22 Steam flow meter and consumption`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: latest flow plus current-shift, previous-shift, and month consumption deltas
  - New-system path: new custom `metric_summary_card` family, steam variant with period deltas

- `#34 Steam flow meter status - v2`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: latest flow/reading, period deltas, and short history trend
  - New-system path: new custom `metric_summary_card` family, steam variant with sparkline

### Status

- `#23 Status - ON/OFF widget`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: latest binary state rendered as an animated toggle card
  - New-system path: direct `StateCard`

- `#24 Open/Close - Doors`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: latest door state rendered as an open/close card
  - New-system path: direct `StateCard` with state mappings

- `#25 Status - History`
  - Legacy panel type: `timeseries`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: state changes over time using Flux `monitor.stateChangesOnly()`
  - New-system path: direct `StateTimeline`

- `#40 Trip/Run - Status`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: latest trip/run state card
  - New-system path: direct `StateCard` with state mappings

### Temperature

- `#30 Temperature - status`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: latest temperature KPI card
  - New-system path: direct `StatusSummary`

- `#33 Temperature - history`
  - Legacy panel type: `timeseries`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: temperature trend chart
  - New-system path: direct `LineChart`

### Preassure

- `#31 Pressure - history`
  - Legacy panel type: `timeseries`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: pressure trend chart
  - New-system path: direct `LineChart`

- `#32 Preassure`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: latest pressure KPI card
  - New-system path: direct `StatusSummary`

- `#36 Steam flow meter status with history`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: steam-style composite card with short history
  - New-system path: new custom `metric_summary_card` family
  - Note: this is attached to `Preassure` in legacy and should be treated as suspicious until validated

- `#37 Preassure with history`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: latest pressure value plus a short history sparkline
  - New-system path: new custom `metric_summary_card` family, pressure variant with sparkline

### IMoni Modbus Level Sensor

- `#38 Level Status`
  - Legacy panel type: `gapit-htmlgraphics-panel`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: latest level plus shift-boundary comparisons
  - New-system path: new custom `metric_summary_card` family, tank-level variant

- `#39 Tank Level`
  - Legacy panel type: `timeseries`
  - Query scope: `{{virtualDeviceId}}`
  - Legacy behavior: level trend chart
  - New-system path: direct `LineChart`

## What the legacy HTML templates have in common

The representative HTML Graphics templates all use the same Grafana plugin shape:

- `type = gapit-htmlgraphics-panel`
- `options.css`
- `options.html`
- `options.onInit`
- `options.onRender`
- `options.codeData`
- `options.dynamicData`
- `options.dynamicProps`

That is good news.

It means Teejay does not need 14 unrelated custom widgets. The legacy set is really a handful of repeated UI families implemented many times through raw Grafana HTML/JS.

The recommendation is:

- do **not** port the raw Grafana HTML/JS into the new system
- do port the **data semantics** and **visual families** into typed widget configs and first-party renderers

## Flux patterns that must be translated

### 1. Latest snapshot reads

Legacy pattern:

- `last()` over `-1h`, `-1d`, or `-99d`

New-system translation:

- use `DeviceLatestReading` where possible
- otherwise use the latest `DeviceTelemetryLog` inside the resolver

Seen in:

- `#8`, `#9`, `#19`, `#20`, `#23`, `#30`, `#32`, `#34`, `#38`

### 2. Period-baseline comparisons

Legacy pattern:

- `first()` after shift start
- `first()` after start of day
- `first()` after start of month
- compare with latest value to derive consumption/production

New-system translation:

- build a reusable `TelemetryPeriodDeltaCalculator`
- query earliest log after a boundary and subtract it from the latest value

Seen in:

- `#8`, `#9`, `#20`, `#22`, `#27`, `#28`, `#34`, `#38`

### 3. Bucketed counter differences

Legacy pattern:

- `aggregateWindow(... )` + `difference()` or start/end subtraction

New-system translation:

- reuse the current `BarChartSnapshotResolver` logic for single-device counters
- extend it for multi-device aggregate counters

Seen in:

- `#7`, `#16`, `#17`

### 4. State-duration utilization

Legacy pattern:

- Flux `events.duration()` over a state stream
- aggregate duration by day or by shift

New-system translation:

- add a reusable `StateDurationCalculator` over ordered `DeviceTelemetryLog` state values
- support configurable shift calendars

Seen in:

- `#10`, `#12`, `#21`, `#27`, `#28`

### 5. Pivoting multiple fields into one snapshot

Legacy pattern:

- `pivot(rowKey:["_time"], columnKey:["_field"], valueColumn:"_value")`

New-system translation:

- the new platform already stores transformed values as a JSON map in `DeviceTelemetryLog.transformed_values`
- if the metrics come from the same logical source, no pivot is needed; just read the keys from one latest log

Seen in:

- `#8`, `#19`, `#34`

### 6. Mini-history windows

Legacy pattern:

- last 12h trend, often downsampled to 5-minute means

New-system translation:

- either extend `LineChartSnapshotResolver` with downsampling
- or provide a reusable mini-history helper inside the custom summary widget resolver

Seen in:

- `#34`, `#37`

### 7. Multi-source widget scopes

Legacy pattern:

- `{{deviceIds}}`
- `{{status.*}}`
- `{{energy.*}}`
- `{{length.*}}`

New-system translation:

- add named widget data scopes instead of relying on a single `device_id`
- each scope should resolve to a device plus its telemetry topic

Seen in:

- `#7`, `#10`, `#11`, `#14`, `#15`, `#21`, `#27`, `#28`

## Recommended widget work in the new system

### A. Reuse current widgets immediately

Seed the following templates using current widgets first:

- `BarChart`: `#16`, `#17`
- `LineChart`: `#18`, `#31`, `#33`, `#39`
- `StateCard`: `#23`, `#24`, `#40`
- `StateTimeline`: `#25`
- `StatusSummary`: `#30`, `#32`

This gives fast coverage for the simple Teejay cases.

### B. Extend current widgets for richer sources

#### Extend `BarChart` to support aggregate scopes

Required for:

- `#7 Hourly Energy Consumption`

Needed capability:

- accept multiple source devices under one widget
- sum per-bucket deltas before returning the series

#### Extend `LineChart` to support named sources

Required for:

- `#11`, `#14`, `#15`

Needed capability:

- each series can resolve from a named source, not only from the widget’s primary `device_id`
- support status overlays and energy/voltage/current overlays from different devices

### C. Add two generic custom widget families

#### 1. `metric_summary_card`

This should be one reusable custom widget family with typed variants, not many one-off widgets.

It should cover:

- `#8 Energy widget`
- `#9 Water widget`
- `#19 Steam flow meter status`
- `#20 Water Flow rate and volume (Status)`
- `#22 Steam flow meter and consumption`
- `#34 Steam flow meter status - v2`
- `#36 Steam flow meter status with history`
- `#37 Preassure with history`
- `#38 Level Status`

Recommended capabilities:

- latest metric tiles
- period delta tiles (`current_shift`, `previous_shift`, `today`, `month`)
- optional sparkline / mini-history
- named data scopes where needed
- configurable unit formatting
- configurable state/color badges

Recommended variants:

- `energy`
- `water`
- `steam`
- `pressure`
- `temperature`
- `tank_level`

#### 2. `utilization_summary_card`

This should handle the Teejay production/utilization cards.

It should cover:

- `#10 Stentner widget`
- `#12 Compressor utilisation widget`
- `#21 Stentner widget (Using length only)`
- `#27 Stentner widget (7.30): v1`
- `#28 Stenter (6:00)`

Recommended capabilities:

- current-state badge
- utilization percentages by day and by shift
- current shift / previous shift / month counters
- configurable shift calendars
- named multi-source bindings (`length`, `status`, optionally `energy`)

## Recommended source-binding shape

Before implementing the Teejay custom cards, add a widget source model that supports named scopes.

Minimum shape:

- `primary`
- optional `status`
- optional `energy`
- optional `length`
- optional aggregate source collections for multi-device bars

Whether this becomes a JSON config block or a dedicated table is an implementation choice, but the capability has to exist before the Stenter and aggregate energy widgets can be recreated cleanly.

## Recommended delivery order

1. Confirm the suspicious legacy links (`#11` and `#36`) before copying them forward.
2. Run a separate Stenter investigation to confirm whether the legacy `Stenter` device type is a virtual/composite device, which source device types feed it, and where that composition is defined.
3. Seed direct-reuse widgets first so Teejay gets immediate baseline coverage.
4. Extend `BarChart` for aggregate multi-device inputs.
5. Extend `LineChart` for named multi-source series.
6. Implement `metric_summary_card`.
7. Implement `utilization_summary_card` with configurable shift calendars.
8. Add a dedicated Teejay dashboard seeder after the widget families are stable.
9. Backfill Teejay widget tests and snapshot parity checks.

## Verification expectations

Each replicated widget family should have:

- resolver-level Pest coverage for latest-value lookup
- resolver-level Pest coverage for shift/day/month baseline calculations
- tests for state-duration calculations
- tests for multi-source widget bindings
- seeder idempotency tests
- a Teejay dashboard seeder test that proves expected widget counts and widget types

## Open questions to resolve before implementation

1. Are `#11 Stentner - current` on `AC Energy Mate` and `#36 Steam flow meter status with history` on `Preassure` real production widgets, or stale/misattached legacy records?
2. For `#7 Hourly Energy Consumption`, do we want to preserve the multi-device aggregate chart exactly, or split it into one chart per device in the new system?
3. For the simple legacy HTML cards (`#23`, `#24`, `#30`, `#32`, `#40`), is functional equivalence enough, or do you want the new UI to visually mimic the old Grafana cards more closely?
4. For Stenter, should shift calendars remain per-widget (`06:00`, `07:30`, two-shift vs three-shift), or should we normalize them into a shared organization/device-type configuration?
5. Is the legacy `Stenter` device type actually a virtual/composite device built from multiple non-virtual devices, and if so, should the new system preserve that abstraction explicitly or flatten it into named widget sources?

## Completion criteria

This Teejay widget replication track is done when:

- every legacy Teejay dashboard template is classified as direct reuse, existing-widget extension, or custom widget family
- named multi-source widget bindings exist in the new system
- the direct replacements are seeded
- the two generic custom widget families cover the remaining Teejay HTML templates without copying raw Grafana HTML/JS
- Teejay dashboard seeding and resolver tests pass
