export function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

export function formatCompactTimestamp(value) {
    if (typeof value !== 'string' || value.trim() === '') {
        return 'No recent data';
    }

    const timestamp = new Date(value);

    if (Number.isNaN(timestamp.getTime())) {
        return 'No recent data';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(timestamp);
}

export function resolvePresenceState(connectionState) {
    const normalizedState = typeof connectionState === 'string'
        ? connectionState.trim().toLowerCase()
        : '';

    if (normalizedState === 'online') {
        return {
            label: 'Online',
            color: '#22c55e',
        };
    }

    if (normalizedState === 'offline') {
        return {
            label: 'Offline',
            color: '#ef4444',
        };
    }

    return {
        label: 'Unknown',
        color: '#64748b',
    };
}

export function renderPresenceMetaMarkup(timestamp, connectionState) {
    const presenceState = resolvePresenceState(connectionState);

    return `
        <div class="iot-status-summary__meta">
            <div class="iot-status-summary__timestamp">${escapeHtml(formatCompactTimestamp(timestamp))}</div>
            <span
                class="iot-status-summary__presence-dot"
                style="--presence-color: ${escapeHtml(presenceState.color)};"
                title="${escapeHtml(presenceState.label)}"
                aria-label="${escapeHtml(presenceState.label)}"
            ></span>
        </div>
    `;
}
