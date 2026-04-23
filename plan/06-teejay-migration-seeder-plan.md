# Teejay Migration Seeders by Device Type

## Summary
Create a top-level `TeejayMigrationSeeder` that orchestrates one seeder per Teejay device type so the migration is modular, testable, and easy to verify against the legacy tenant.

The seeders should cover all Teejay device types found in the legacy MySQL tenant, not just the current special-conversion IMONI hubs. The current Teejay footprint includes these relevant device types:

- `IMoni Hub`
- `AC Energy Mate`
- `Modbus`
- `Water Flow and Volume`
- `IMoni Modbus Level Sensor`
- `Fabric Length`
- `Fabric Length(Short)`
- `Status`
- `Temperature`
- `Steam meter`
- `Stenter`
- `Pressure` if any Teejay devices are found during final implementation pass

## Seeder Structure

### 1. Add a root Teejay seeder

- Create `database/seeders/TeejayMigrationSeeder.php`.
- Responsibilities:
  - upsert the `Teejay` organization
  - call all Teejay device-type seeders in a fixed order
  - own shared Teejay constants such as organization slug/name
  - own shared cleanup/pruning entrypoints if needed

### 2. Split child onboarding by device type

Create dedicated seeders, each responsible for one device type and only that device type's schemas, devices, bindings, and mutations:

- `TeejayHubsSeeder`
- `TeejayAcEnergyMateSeeder`
- `TeejayModbusSeeder`
- `TeejayWaterFlowVolumeSeeder`
- `TeejayModbusLevelSensorSeeder`
- `TeejayFabricLengthSeeder`
- `TeejayFabricLengthShortSeeder`
- `TeejayStatusSeeder`
- `TeejayTemperatureSeeder`
- `TeejaySteamMeterSeeder`
- `TeejayStenterSeeder`

Each seeder should:

- create or update the needed global/shared device type if it belongs in the new platform catalog
- create or update the schema version(s) for that device type
- create or update Teejay devices of that type
- create `DeviceSignalBinding` rows from normalized source topics
- define parameter mutations and decode rules needed by that device type
- prune stale Teejay artifacts for that device type only

### 3. Shared Teejay source inventory

- Add a shared Teejay inventory source in code, preferably a dedicated support class or constant map used by the seeders.
- Group inventory first by hub IMEI, then by peripheral type, then by logical child device.
- Include legacy facts needed for migration:
  - legacy `device_uid`
  - hub IMEI
  - peripheral type hex
  - legacy parameter paths
  - decode mode if required
  - mutation/calibration rules
  - display labels

## Migration Rules by Device Type

### Hubs

- Seed all Teejay IMONI hubs as parent devices.
- Preserve hub presence behavior and parent-child relationships.
- Include the special-conversion hub set already confirmed from legacy:
  - `869604063871346`
  - `869604063859564`
  - `869604063870249`
  - `869604063874209`
  - `169604063874209`
  - `869604063849748`
  - `869604063845217`

### Flow / volume / Modbus-backed devices

- Bind from normalized source topics like `migration/source/imoni/{imei}/{peripheral}/telemetry`.
- Support Laravel-side raw hex decode where transport decoding is required.
- Port legacy flow scaling such as `flow * 3600` into parameter-level mutations.
- Keep passthrough numeric binding available for devices that do not need raw-hex decoding.

### Tank level devices

- Port legacy `level1` / `level2` mappings and their linear calibrations into platform mutations.
- Distinguish transport decode from level calibration.
- Support duplicate or ambiguous legacy child IDs carefully and seed one canonical device per intended logical asset.

### AC energy devices

- Seed AC energy device schemas using normalized named/numeric bindings.
- Apply parameter mutations only where legacy behavior requires scaling.
- Keep object-value bindings available for named payload keys when present.

### Status / Fabric length / Temperature / Steam

- Port legacy parameter mapping and any simple conditional calibration.
- Keep these seeders separate even if they share source topics so verification stays device-type-scoped.

## Testing and Verification

- Add one feature test per Teejay device-type seeder verifying:
  - devices are created
  - schema versions are attached
  - bindings are correct
  - mutations/decode config is correct
- Add end-to-end ingestion tests for each Teejay device type with representative source payloads.
- Add targeted tests for the special-conversion hubs to prove Laravel-side decode replaces Node-RED per-IMEI conversion.
- Add reseed/idempotency tests for each seeder so stale artifacts are removed safely.
- Add a top-level `TeejayMigrationSeederTest` proving the orchestrator seeds every Teejay device type and expected counts.

## Assumptions

- `TeejayMigrationSeeder` should call device-type seeders, not hub-specific seeders.
- Verification should be device-type-oriented so migration progress is easy to audit.
- Legacy `conditionalCalibrations` should be migrated into platform parameter mutation rules.
- Raw transport decode support still needs to be added in Laravel for the subset of Teejay hubs currently handled by Node-RED conversion profiles.
