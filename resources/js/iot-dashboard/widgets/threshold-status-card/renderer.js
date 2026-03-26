import { escapeHtml, formatCompactTimestamp, renderPresenceMetaMarkup } from '../shared/meta';

function resolveStatusTone(status) {
    const normalizedStatus = typeof status === 'string'
        ? status.trim().toLowerCase()
        : '';

    if (normalizedStatus === 'alert') {
        return 'is-alert';
    }

    if (normalizedStatus === 'normal') {
        return 'is-normal';
    }

    if (normalizedStatus === 'offline') {
        return 'is-offline';
    }

    if (normalizedStatus === 'inactive') {
        return 'is-inactive';
    }

    return 'is-no-data';
}

export function renderThresholdStatusCardMarkup(widget) {
    const card = widget?.card && typeof widget.card === 'object' ? widget.card : null;

    if (!card) {
        return `
            <div class="iot-threshold-status-card iot-threshold-status-card--empty">
                <div class="iot-threshold-status-card__empty">No threshold policy is connected to this widget.</div>
            </div>
        `;
    }

    const ruleLabel = typeof card.rule_label === 'string' && card.rule_label.trim() !== ''
        ? card.rule_label.trim()
        : 'Custom rule';
    const policyId = Number(card?.policy_id ?? 0);
    const statusLabel = resolveStatusLabel(card);
    const currentValueDisplay = typeof card.current_value_display === 'string' && card.current_value_display.trim() !== ''
        ? card.current_value_display.trim()
        : '—';

    return `
        <article class="iot-threshold-status-card ${resolveStatusTone(card.status)}">
            <header class="iot-threshold-status-card__header">
                ${renderPresenceMetaMarkup(card.last_telemetry_at, card.connection_state)}
            </header>
            ${renderRuleMarkup(ruleLabel, policyId)}
            <div class="iot-threshold-status-card__status ${resolveStatusTone(card.status)}">${escapeHtml(statusLabel)}</div>
            <div class="iot-threshold-status-card__value">${escapeHtml(currentValueDisplay)}</div>
        </article>
    `;
}

function renderRuleMarkup(ruleLabel, policyId) {
    if (!Number.isInteger(policyId) || policyId <= 0) {
        return `<div class="iot-threshold-status-card__rule">${escapeHtml(ruleLabel)}</div>`;
    }

    return `
        <button
            type="button"
            class="iot-threshold-status-card__rule iot-threshold-status-card__rule-action"
            data-iot-threshold-policy-edit="${escapeHtml(String(policyId))}"
        >
            ${escapeHtml(ruleLabel)}
        </button>
    `;
}

function resolveStatusLabel(card) {
    const normalizedStatus = typeof card?.status === 'string'
        ? card.status.trim().toLowerCase()
        : '';
    const alertTriggeredAt = typeof card?.alert_triggered_at === 'string' && card.alert_triggered_at.trim() !== ''
        ? card.alert_triggered_at.trim()
        : null;
    const rawStatusLabel = typeof card?.status_label === 'string' && card.status_label.trim() !== ''
        ? card.status_label.trim()
        : 'NO DATA';

    if (normalizedStatus === 'alert') {
        return alertTriggeredAt ? formatCompactTimestamp(alertTriggeredAt) : 'Alerted';
    }

    if (normalizedStatus === 'normal') {
        return 'Normal';
    }

    return rawStatusLabel
        .toLowerCase()
        .split(/\s+/)
        .filter((segment) => segment !== '')
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}
