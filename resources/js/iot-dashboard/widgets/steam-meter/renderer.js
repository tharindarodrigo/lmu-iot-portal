import { escapeHtml, renderPresenceMetaMarkup } from '../shared/meta';

function formatNumber(value, options = {}) {
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
        return '—';
    }

    return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: options.minimumFractionDigits ?? 0,
        maximumFractionDigits: options.maximumFractionDigits ?? 1,
    }).format(numericValue);
}

function renderMetric(label, value, unit, icon = '') {
    return `
        <div class="iot-steam-meter__metric">
            ${icon !== '' ? `<span class="iot-steam-meter__metric-icon">${escapeHtml(icon)}</span>` : ''}
            <div>
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)} <small>${escapeHtml(unit)}</small></strong>
            </div>
        </div>
    `;
}

export function renderSteamMeterMarkup(widget) {
    const card = widget?.card && typeof widget.card === 'object' ? widget.card : null;

    if (!card) {
        return '<div class="iot-steam-meter iot-steam-meter--empty">NO DATA</div>';
    }

    return `
        <article class="iot-steam-meter">
            ${renderPresenceMetaMarkup(card.recorded_at, widget?.device_connection_state)}

            <section class="iot-steam-meter__total" aria-label="Total steam consumption">
                <span class="iot-steam-meter__total-icon">▣</span>
                <strong>${escapeHtml(formatNumber(card.total_tons, { maximumFractionDigits: 0 }))}</strong>
                <span>tons</span>
            </section>

            <section class="iot-steam-meter__flow" aria-label="Current steam flow rate">
                <span>Current flow rate</span>
                <strong>${escapeHtml(formatNumber(card.current_flow_rate, { minimumFractionDigits: 1, maximumFractionDigits: 2 }))}</strong>
                <small>kg/hr</small>
            </section>

            <section class="iot-steam-meter__metrics" aria-label="Steam meter consumption summaries">
                ${renderMetric('Monthly', formatNumber(card.monthly_kg), 'kg', '▦')}
                ${renderMetric('Current Shift', formatNumber(card.current_shift?.kg), 'kg', '▣')}
                ${renderMetric('Previous Shift', formatNumber(card.previous_shift?.kg), 'kg', '▣')}
            </section>
        </article>
    `;
}