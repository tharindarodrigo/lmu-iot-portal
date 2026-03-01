# Cloud IoT Energy Platform - 14 Minute Presentation + Demo Plan

## 1) Team Roles and Time Split

| Member | Role | Duration |
|---|---|---:|
| Member 1 | Presentation Part A (problem, architecture, firmware strategy) | 2 min 30 sec |
| Member 2 | Presentation Part B (automation logic, evidence, handoff) | 2 min 30 sec |
| Member 3 (You) | Live demo (simulated energy device + end-to-end flow) | 9 min 00 sec |
| **Total** |  | **14 min 00 sec** |

## 2) Core Message to Deliver

This is not just a dashboard project. It is an end-to-end, auditable IoT operations platform where telemetry is converted into automated, traceable control actions.

## 3) 14-Minute Run of Show (Exact Timeline)

| Time | Speaker | What Happens | Key Line to Say |
|---|---|---|---|
| 00:00-00:40 | Member 1 | Opening + problem framing | "Most IoT systems stop at visualization. We built a system that senses, decides, and acts." |
| 00:40-01:40 | Member 1 | Show end-to-end architecture (Figure 2) | "Telemetry, automation, control dispatch, feedback reconciliation, and reporting are in one platform boundary." |
| 01:40-02:30 | Member 1 | Explain firmware architecture (common runtime + device modules) | "We separated reusable ESP32 connectivity/runtime logic from device-specific modules." |
| 02:30-03:20 | Member 2 | Explain 15-minute automation rule + command lifecycle | "The system computes a windowed energy delta and triggers RGB control only when threshold conditions pass." |
| 03:20-04:20 | Member 2 | Show evidence views: dashboard, control lifecycle, reporting | "Every decision branch is visible: telemetry in, rule evaluation, command out, and device state back." |
| 04:20-05:00 | Member 2 | Demo handoff + success criteria | "Watch for four checkpoints: telemetry spike, rule pass, command dispatch, and feedback acknowledgment." |
| 05:00-14:00 | Member 3 | Live demo | End-to-end simulated operation proving ingestion -> decision -> action -> evidence |

## 4) Slide Plan for the First 5 Minutes (Members 1 and 2)

Use 5 slides max to keep pace.

### Slide 1 (00:00-00:40) - Problem and Objective
- Telemetry-only systems are slow for operations.
- Project objective: automatic reaction based on energy behavior.
- Scenario: 15-minute consumption rule triggers RGB actuator command.

### Slide 2 (00:40-01:40) - System Architecture (from `docs/report-images/figure2-end-to-end-architecture.png`)
- Device telemetry via MQTT-style topics.
- Ingestion + validation + persistence.
- Automation runtime + command dispatch.
- Dashboard + reporting from the same canonical telemetry source.

### Slide 3 (01:40-02:30) - Firmware Strategy
- Common ESP32 runtime handles WiFi/MQTT lifecycle, LWT/presence, retry behavior.
- RGB actuator module handles control + acknowledgment.
- Single-phase meter module handles PZEM RS485 Modbus read path.
- Benefit: reusable onboarding and lower fleet maintenance cost.

### Slide 4 (02:30-03:20) - Automation Logic
- Rolling 15-minute computation: `MAX(total_energy_kwh) - MIN(total_energy_kwh)`.
- Pass branch -> dispatch alert command to RGB device.
- Fail branch -> no command (safe negative path).
- All steps are traceable in run-step and command lifecycle records.

### Slide 5 (03:20-05:00) - Evidence + Demo Setup
- Show screenshots quickly:
  - Dashboard (`figure4-iot-dashboard.png`)
  - Control feedback (`figure5-device-control-feedback.png`)
  - Reports (`figure6-reports-page.png`)
- State the live success criteria before handoff.

## 5) 9-Minute Demo Script (Member 3)

### Demo Goal
Prove that simulated telemetry can trigger deterministic automation and produce auditable control/reporting evidence.

### 05:00-05:50 - Baseline State
- Open dashboard and show current normal values.
- Open automation/control view in another tab.
- Say: "We start from a safe baseline with no alert command active."

### 05:50-06:50 - Start Simulation
- Start/publish simulated meter stream with increasing `total_energy_kwh`.
- Mention key telemetry fields: `voltage_v`, `current_a`, `active_power_w`, `total_energy_kwh`, `read_ok`.
- Say: "This is a simulated meter device, using the same contract as a real device."

### 06:50-08:00 - Show Ingestion and Realtime Updates
- Show new points appearing in dashboard/realtime widgets.
- Confirm ingestion continuity and no manual refresh dependency.
- Say: "The platform is ingesting and visualizing in near real-time."

### 08:00-09:20 - Trigger the 15-Minute Rule
- Show the automation run / condition evaluation.
- Highlight threshold pass event.
- Say: "Now the 15-minute delta crosses threshold, so the workflow moves to command dispatch."

### 09:20-10:30 - Command Dispatch to RGB Actuator
- Show command created/sent.
- Show actuator state update/acknowledgment.
- Say: "This confirms control is based on validated logic, not just dashboard observation."

### 10:30-11:40 - Lifecycle and Feedback Reconciliation
- Show command lifecycle states and matching feedback record.
- Emphasize traceability.
- Say: "We can audit what was sent, when it was sent, and what the device reported back."

### 11:40-12:30 - Reporting Evidence
- Generate/open CSV report output from reporting pipeline.
- Show status progression (`queued`, `running`, `completed`).
- Say: "The same telemetry also feeds asynchronous export evidence for operations and reporting."

### 12:30-13:20 - Negative Path (No False Trigger)
- Reduce simulation load or show a run where condition does not pass.
- Confirm no control command is sent.
- Say: "When threshold does not pass, the system correctly avoids side effects."

### 13:20-14:00 - Close
- Summarize in one sentence: "We demonstrated full-loop IoT operations: ingest, decide, act, and verify."
- Invite Q&A.

## 6) Demo Safety Checklist (Do This Before Presenting)

### 30-60 minutes before
- Confirm platform services are running.
- Confirm at least one dashboard dataset is visible.
- Confirm automation workflow is enabled and threshold is set for a visible demo trigger.
- Confirm simulated device publishing works on the expected topic.
- Confirm RGB control target device is online/presence visible.

### 10 minutes before
- Open tabs in order:
  1. Dashboard
  2. Automation run/condition view
  3. Device control lifecycle view
  4. Reports page
- Pre-position windows to avoid navigation delay.
- Clear stale runs/log clutter if needed so new evidence is obvious.

## 7) Backup Plan (If Live Demo Fails)

If any live part fails, continue without stopping the flow:
- Use existing screenshots from `docs/report-images/` in this order:
  1. `figure4-iot-dashboard.png`
  2. `figure3-automation-dag.png`
  3. `figure5-device-control-feedback.png`
  4. `figure6-reports-page.png`
- Narrate the same four checkpoints: telemetry ingestion, rule pass, command dispatch, feedback/report evidence.
- Keep the close unchanged.

## 8) Short Speaking Script by Person

### Member 1 (2:30)
"Our project addresses a common IoT gap: systems that monitor but do not react. We built an end-to-end platform where telemetry is processed into deterministic automation. The architecture integrates ingestion, automation, control, dashboarding, and reporting. On the edge side, we use a reusable ESP32 runtime and separate device modules, so onboarding new devices does not require rewriting connectivity behavior."

### Member 2 (2:30)
"The core rule computes 15-minute consumption from cumulative energy and triggers control only when conditions pass. This gives both positive and safe negative paths. We also preserve full traceability through run-step and command lifecycle records, then expose both realtime and asynchronous evidence. In the demo, you should see telemetry spike, rule pass, command dispatch, and verified feedback."

### Member 3 (9:00)
"I will now run the live simulation and show the full loop from telemetry to verified control action and reporting evidence."
