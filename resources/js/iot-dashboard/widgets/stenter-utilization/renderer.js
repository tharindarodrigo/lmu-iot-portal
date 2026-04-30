import { escapeHtml } from '../shared/meta';

function formatPercent(value) {
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
        return '—';
    }

    return `${numericValue.toFixed(1)}%`;
}

function formatDuration(minutes) {
    const numericMinutes = Number(minutes);

    if (!Number.isFinite(numericMinutes) || numericMinutes < 0) {
        return '—';
    }

    const totalMinutes = Math.round(numericMinutes);
    const hours = Math.floor(totalMinutes / 60);
    const remainingMinutes = totalMinutes % 60;

    return `${String(hours).padStart(2, '0')}:${String(remainingMinutes).padStart(2, '0')}`;
}

function formatLength(counter) {
    const value = Number(counter?.value);
    const unit = typeof counter?.unit === 'string' && counter.unit.trim() !== '' ? counter.unit.trim() : 'm';

    if (!Number.isFinite(value)) {
        return '—';
    }

    return `${new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 1,
    }).format(value)} <small>${escapeHtml(unit)}</small>`;
}

function formatLocalTime(timestamp) {
    if (typeof timestamp !== 'string' || timestamp.trim() === '') {
        return '—';
    }

    const date = new Date(timestamp);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(date);
}

function formatShiftRange(shift) {
    return `${formatLocalTime(shift?.start_at)} → ${formatLocalTime(shift?.end_at)}`;
}

function resolveState(card) {
    const state = card?.state && typeof card.state === 'object' ? card.state : null;

    return {
        label: typeof state?.label === 'string' && state.label.trim() !== '' ? state.label.trim() : 'No Data',
        color: typeof state?.color === 'string' && state.color.trim() !== '' ? state.color.trim() : '#64748b',
        isRunning: Boolean(state?.is_running),
    };
}

function thresholdColor(card, value) {
    const numericValue = Number(value);
    const thresholds = Array.isArray(card?.percentage_thresholds) ? card.percentage_thresholds : [];

    if (!Number.isFinite(numericValue)) {
        return '#64748b';
    }

    const threshold = thresholds.find((candidate) => {
        const minimum = Number(candidate?.minimum);
        const maximum = Number(candidate?.maximum);

        return Number.isFinite(minimum)
            && Number.isFinite(maximum)
            && numericValue >= minimum
            && numericValue <= maximum;
    });

    return typeof threshold?.color === 'string' && threshold.color.trim() !== ''
        ? threshold.color.trim()
        : '#f59e0b';
}

function renderSegments(card) {
    const segments = Array.isArray(card?.status_segments) ? card.status_segments : [];

    if (segments.length === 0) {
        return '<div class="iot-stenter-utilization__no-status">NO STATUS DATA</div>';
    }

    return segments
        .map((segment) => {
            const state = segment?.state === 'off' ? 'off' : 'on';
            const left = Number(segment?.start_percent ?? 0);
            const width = Number(segment?.width_percent ?? 0);

            return `<i class="iot-stenter-utilization__segment is-${state}" style="left: ${Math.max(0, Math.min(100, left))}%; width: ${Math.max(0, Math.min(100, width))}%;"></i>`;
        })
        .join('');
}

function renderDailyEfficiencies(card) {
    const days = Array.isArray(card?.daily_efficiencies) ? card.daily_efficiencies : [];

    return days
        .slice(-3)
        .map((day) => {
            const color = thresholdColor(card, day?.efficiency_percent);

            return `
            <div class="iot-stenter-utilization__efficiency" style="--stenter-percentage-color: ${escapeHtml(color)};">
                <span>${escapeHtml(typeof day?.label === 'string' ? day.label : '')}</span>
                <div class="iot-stenter-utilization__meter"><i style="width: ${Math.max(0, Math.min(100, Number(day?.efficiency_percent ?? 0)))}%;"></i></div>
                <strong>${escapeHtml(formatPercent(day?.efficiency_percent))}</strong>
            </div>
        `;
        })
        .join('');
}

export function renderStenterUtilizationMarkup(widget) {
    const card = widget?.card && typeof widget.card === 'object' ? widget.card : null;

    if (!card) {
        return '<div class="iot-stenter-utilization iot-stenter-utilization--empty">NO DATA</div>';
    }

    const state = resolveState(card);
    const currentShift = card?.current_shift && typeof card.current_shift === 'object' ? card.current_shift : {};
    const counters = card?.length_counters && typeof card.length_counters === 'object' ? card.length_counters : {};
    const currentShiftColor = thresholdColor(card, currentShift?.efficiency_percent);

    return `
        <article class="iot-stenter-utilization">
            <div class="iot-stenter-utilization__state" style="--stenter-state-color: ${escapeHtml(state.color)};">
                ${escapeHtml(state.label)}
            </div>

            <section class="iot-stenter-utilization__shift" aria-label="Current shift">
                <div class="iot-stenter-utilization__shift-time">
                    <span>${escapeHtml(typeof currentShift?.label === 'string' ? currentShift.label : 'Current Shift')}</span>
                    <strong>${escapeHtml(formatShiftRange(currentShift))}</strong>
                </div>
                <strong class="iot-stenter-utilization__shift-value" style="--stenter-percentage-color: ${escapeHtml(currentShiftColor)};">${escapeHtml(formatPercent(currentShift?.efficiency_percent))}</strong>
            </section>

            <section class="iot-stenter-utilization__status" aria-label="Last hour status">
                <div class="iot-stenter-utilization__section-label">Status</div>
                <div class="iot-stenter-utilization__status-meta">
                    <span>Last hour</span>
                    <span>1 min</span>
                </div>
                <div class="iot-stenter-utilization__chart" role="img" aria-label="ON OFF status switching chart">
                    ${renderSegments(card)}
                </div>
            </section>

            <section class="iot-stenter-utilization__durations" aria-label="Current shift status duration summary">
                <div class="iot-stenter-utilization__tile">
                    <span>↻ Run time</span>
                    <strong>${escapeHtml(formatDuration(currentShift?.run_minutes))}</strong>
                </div>
                <div class="iot-stenter-utilization__tile">
                    <span>↺ Idle time</span>
                    <strong>${escapeHtml(formatDuration(currentShift?.idle_minutes))}</strong>
                </div>
            </section>

            <div class="iot-stenter-utilization__section-label">Efficiency</div>
            <section class="iot-stenter-utilization__efficiencies" aria-label="Last three days efficiencies">
                ${renderDailyEfficiencies(card)}
            </section>

            <div class="iot-stenter-utilization__section-label">Fabric Length</div>
            <section class="iot-stenter-utilization__lengths" aria-label="Fabric counter deltas">
                <div class="iot-stenter-utilization__tile is-wide">
                    <span>▦ This month</span>
                    <strong>${formatLength(counters?.month)}</strong>
                </div>
                <div class="iot-stenter-utilization__tile">
                    <span>◷ Current Shift</span>
                    <strong>${formatLength(counters?.current_shift)}</strong>
                </div>
                <div class="iot-stenter-utilization__tile">
                    <span>◴ Previous Shift</span>
                    <strong>${formatLength(counters?.previous_shift)}</strong>
                </div>
            </section>
        </article>
    `;
}
