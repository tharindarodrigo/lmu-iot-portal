<div {{ $attributes->class('iot-history-range') }} data-iot-history-range>
    <button
        type="button"
        class="iot-history-range__trigger"
        data-iot-history-trigger
        aria-haspopup="dialog"
        aria-expanded="false"
    >
        <span class="iot-history-range__trigger-icon">
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedClock" class="size-4" />
        </span>
        <span data-iot-history-trigger-label>{{ $triggerLabel ?? 'Last 6 hours' }}</span>
    </button>

    <div class="iot-history-range__popover" data-iot-history-popover hidden>
        <div class="iot-history-range__popover-panel">
            <div class="iot-history-range__pane">
                <p class="iot-history-range__eyebrow">Time range</p>

                <div data-iot-history-relative-pane>
                    <label class="iot-history-range__field">
                        <span>From</span>
                        <input type="text" readonly data-iot-history-relative-from>
                    </label>
                    <label class="iot-history-range__field">
                        <span>To</span>
                        <input type="text" readonly data-iot-history-relative-until>
                    </label>
                </div>

                <div data-iot-history-absolute-pane hidden>
                    <label class="iot-history-range__field">
                        <span>From</span>
                        <input type="datetime-local" data-iot-history-absolute-from>
                    </label>
                    <label class="iot-history-range__field">
                        <span>To</span>
                        <input type="datetime-local" data-iot-history-absolute-until>
                    </label>
                </div>

                <p class="iot-history-range__error" data-iot-history-error hidden></p>

                <div class="iot-history-range__actions">
                    <button type="button" class="iot-history-range__apply" data-iot-history-apply>
                        Apply time range
                    </button>
                    <button type="button" class="iot-history-range__reset" data-iot-history-reset>
                        Reset
                    </button>
                </div>
            </div>

            <div class="iot-history-range__quick-ranges">
                <p class="iot-history-range__eyebrow">Quick ranges</p>

                <div class="iot-history-range__quick-list">
                    @foreach ($historyPresets as $historyPreset)
                        <button
                            type="button"
                            class="iot-history-range__quick-button"
                            data-iot-history-preset="{{ $historyPreset->value }}"
                        >
                            {{ $historyPreset->getLabel() }}
                        </button>
                    @endforeach

                    <button
                        type="button"
                        class="iot-history-range__quick-button"
                        data-iot-history-custom
                    >
                        Custom range
                    </button>
                </div>
            </div>
        </div>

        <div class="iot-history-range__footer">
            <span class="iot-history-range__footer-label">Browser Time</span>
            <span data-iot-history-timezone>—</span>
        </div>
    </div>
</div>
