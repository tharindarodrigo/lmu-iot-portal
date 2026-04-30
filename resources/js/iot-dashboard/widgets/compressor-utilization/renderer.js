import { escapeHtml } from '../shared/meta';

function renderPercentMarkup(value) {
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
        return '—';
    }

    return `<span class="iot-compressor-utilization__percent-number">${escapeHtml(numericValue.toFixed(1))}</span><span class="iot-compressor-utilization__percent-symbol">%</span>`;
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

function normalizeSegmentPercent(value) {
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
        return 0;
    }

    return Math.max(0, Math.min(100, numericValue));
}

function mergeStatusSegments(segments) {
    return segments
        .map((segment) => {
            const state = segment?.state === 'off' ? 'off' : 'on';
            const left = normalizeSegmentPercent(segment?.start_percent ?? 0);
            const width = normalizeSegmentPercent(segment?.width_percent ?? 0);

            return {
                state,
                left,
                width: Math.max(0, Math.min(100 - left, width)),
            };
        })
        .filter((segment) => segment.width > 0)
        .sort((first, second) => first.left - second.left)
        .reduce((merged, segment) => {
            const previous = merged.at(-1);
            const previousEnd = previous ? previous.left + previous.width : 0;

            if (previous && previous.state === segment.state && segment.left <= previousEnd + 0.1) {
                previous.width = Math.max(previous.width, (segment.left + segment.width) - previous.left);

                return merged;
            }

            merged.push({ ...segment });

            return merged;
        }, []);
}

function renderSegments(card) {
    const segments = Array.isArray(card?.status_segments) ? card.status_segments : [];

    if (segments.length === 0) {
        return '<div class="iot-compressor-utilization__no-status">NO STATUS DATA</div>';
    }

    return mergeStatusSegments(segments)
        .map((segment) => `<i class="iot-compressor-utilization__segment is-${segment.state}" style="left: ${segment.left}%; width: ${segment.width}%;"></i>`)
        .join('');
}

function renderDailyUtilizations(card) {
    const days = Array.isArray(card?.daily_utilizations) ? card.daily_utilizations : [];

    return days
        .slice(-3)
        .map((day) => {
            const color = thresholdColor(card, day?.utilization_percent);

            return `
            <div class="iot-compressor-utilization__day" style="--compressor-percentage-color: ${escapeHtml(color)};">
                <span>${escapeHtml(typeof day?.label === 'string' ? day.label : '')}</span>
                <strong>${renderPercentMarkup(day?.utilization_percent)}</strong>
            </div>
        `;
        })
        .join('');
}

export function renderCompressorUtilizationMarkup(widget) {
    const card = widget?.card && typeof widget.card === 'object' ? widget.card : null;

    if (!card) {
        return '<div class="iot-compressor-utilization iot-compressor-utilization--empty">NO DATA</div>';
    }

    const state = resolveState(card);
    const currentShift = card?.current_shift && typeof card.current_shift === 'object' ? card.current_shift : {};
    const currentShiftColor = thresholdColor(card, currentShift?.utilization_percent);

    return `
        <article class="iot-compressor-utilization">
            <div class="iot-compressor-utilization__state" style="--compressor-state-color: ${escapeHtml(state.color)};">
                ${escapeHtml(state.label)}
            </div>

            <section class="iot-compressor-utilization__shift" aria-label="Current shift compressor utilization">
                <div class="iot-compressor-utilization__shift-caption">
                    <span>${escapeHtml(typeof currentShift?.label === 'string' ? currentShift.label : 'Current Shift')}</span>
                    <strong>${escapeHtml(formatShiftRange(currentShift))}</strong>
                </div>
                <div class="iot-compressor-utilization__gauge" style="--compressor-percentage-color: ${escapeHtml(currentShiftColor)}; --compressor-percentage: ${Math.max(0, Math.min(100, Number(currentShift?.utilization_percent) || 0))};">
                    <strong>${renderPercentMarkup(currentShift?.utilization_percent)}</strong>
                </div>
            </section>

            <section class="iot-compressor-utilization__durations" aria-label="Current shift runtime and idle time">
                <div class="iot-compressor-utilization__tile">
                    <span>↻ Run time</span>
                    <strong>${escapeHtml(formatDuration(currentShift?.run_minutes))}</strong>
                </div>
                <div class="iot-compressor-utilization__tile">
                    <span>↺ Idle time</span>
                    <strong>${escapeHtml(formatDuration(currentShift?.idle_minutes))}</strong>
                </div>
            </section>

            <section class="iot-compressor-utilization__status" aria-label="Last hour status">
                <div class="iot-compressor-utilization__section-label">Status</div>
                <div class="iot-compressor-utilization__status-meta">
                    <span>Last hour</span>
                    <span>Derived status</span>
                </div>
                <div class="iot-compressor-utilization__chart" role="img" aria-label="Compressor running and off status chart">
                    ${renderSegments(card)}
                </div>
            </section>

            <div class="iot-compressor-utilization__section-label">Last 3 days performance</div>
            <section class="iot-compressor-utilization__days" aria-label="Last three days compressor utilization">
                ${renderDailyUtilizations(card)}
            </section>
        </article>
    `;
}