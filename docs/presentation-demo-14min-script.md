# Presentation Script (Slide-by-Slide)

## Slide 1 - Cloud IoT Energy Monitoring and Automation Platform
Today we are presenting our cloud IoT energy monitoring and automation platform. The key point is that this system does not stop at visualization. It takes telemetry, evaluates a rule, sends a control command, and verifies the response. For the demo, we will use a simulated energy device so you can see the same end-to-end flow used for real devices.

## Slide 2 - Problem and Objective
The problem we solved is operational delay. In many IoT setups, teams watch dashboards and manually react. Our objective was to make this deterministic. We implemented a 15-minute energy rule that triggers control only when the condition is met, and we record every decision and outcome for auditability.

## Slide 3 - End-to-End Architecture
This diagram shows the full path. Devices publish telemetry through MQTT-style topics, ingestion validates and stores canonical records, and automation evaluates rules before dispatching control commands. Dashboards and reports read from the same data source, so monitoring, control, and evidence stay consistent.

## Slide 4 - Firmware Strategy
On the firmware side, we use a shared runtime and separate device modules. The shared runtime handles WiFi, MQTT, presence, and reconnect behavior. The RGB module handles control and acknowledgments, and the PZEM module handles Modbus telemetry polling. This approach makes onboarding new devices faster and keeps v1 meter behavior safely read-only.

## Slide 5 - Automation Logic
This is the exact rule in our demo: the 15-minute consumption delta is MAX(total_energy_kwh) minus MIN(total_energy_kwh). If that value crosses the threshold, the workflow dispatches an RGB alert command. If it does not cross, no command is sent. Both branches are explicit and every step is logged through run records and command lifecycle states.

## Slide 6 - Demo Walkthrough
Now for the demo flow. We start from a normal baseline, publish simulated meter telemetry with rising energy values, and observe real-time updates and rule evaluation. Then we verify command dispatch and acknowledgment, and finish with reporting evidence plus a safe negative-path case where no command is triggered.

## Slide 7 - Deployment and Scalability
This architecture is designed to scale beyond the classroom demo. Ingestion, automation, and reporting workloads can scale independently, and the containerized design stays cloud-agnostic and Kubernetes-ready. That gives us a practical path from prototype behavior to production-style operations.

## Slide 8 - Conclusion
To conclude, we demonstrated a full-loop IoT workflow: ingest, decide, act, and verify. The platform combines automation with traceability, so outcomes are operationally useful and auditable. The same architecture and firmware pattern can be reused for future devices and larger deployments. Thank you, and we welcome your questions.
