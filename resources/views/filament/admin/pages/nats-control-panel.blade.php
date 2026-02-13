<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section
            heading="NATS Key Value Topics"
            description="Browse, load, and upsert topic payloads in the device state KV bucket."
        >
            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="space-y-1 text-sm">
                        <span class="font-medium text-gray-700 dark:text-gray-300">NATS Host</span>
                        <input
                            type="text"
                            wire:model.live="natsHost"
                            class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                        />
                    </label>

                    <label class="space-y-1 text-sm">
                        <span class="font-medium text-gray-700 dark:text-gray-300">NATS Port</span>
                        <input
                            type="number"
                            wire:model.live="natsPort"
                            class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                        />
                    </label>
                </div>

                <label class="space-y-1 text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Device UUID</span>
                    <input
                        type="text"
                        wire:model.live="deviceUuid"
                        class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    />
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Topic</span>
                    <input
                        type="text"
                        wire:model.live="topic"
                        placeholder="devices.pump-42.status"
                        class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    />
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Payload JSON</span>
                    <textarea
                        rows="8"
                        wire:model.live="payloadJson"
                        class="fi-input block w-full rounded-lg border-gray-300 font-mono text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    ></textarea>
                </label>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button wire:click="refreshTopicStates" color="gray">
                        Refresh Device Topics
                    </x-filament::button>

                    <x-filament::button wire:click="loadTopicPayload" color="info">
                        Load Topic Payload
                    </x-filament::button>

                    <x-filament::button wire:click="upsertTopicPayload" color="success">
                        Upsert Topic Payload
                    </x-filament::button>
                </div>

                <div class="rounded-xl border border-gray-200 dark:border-gray-700">
                    <div class="border-b border-gray-200 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-300">
                        Cached Topic States
                    </div>
                    <div class="max-h-72 overflow-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-900/70">
                                    <th class="px-3 py-2 font-semibold text-gray-500 dark:text-gray-300">Topic</th>
                                    <th class="px-3 py-2 font-semibold text-gray-500 dark:text-gray-300">Stored At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->topicStates as $state)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2 font-mono text-[11px] text-gray-700 dark:text-gray-200">{{ $state['topic'] }}</td>
                                        <td class="px-3 py-2 text-gray-500 dark:text-gray-300">{{ $state['stored_at'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="px-3 py-3 text-gray-500 dark:text-gray-400">
                                            No topic states loaded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="Device MQTT Credentials"
            description="Quickly manage MQTT usernames and passwords on device types used by devices."
        >
            <div class="space-y-4">
                <label class="space-y-1 text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Device Type</span>
                    <select
                        wire:model.live="deviceTypeId"
                        class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    >
                        <option value="">Select Device Type</option>
                        @foreach ($this->deviceTypeOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">MQTT Username</span>
                    <input
                        type="text"
                        wire:model.live="mqttUsername"
                        class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    />
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">MQTT Password</span>
                    <input
                        type="password"
                        wire:model.live="mqttPassword"
                        class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    />
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Base Topic</span>
                    <input
                        type="text"
                        wire:model.live="mqttBaseTopic"
                        class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    />
                </label>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button wire:click="loadMqttCredentials" color="gray">
                        Load Credentials
                    </x-filament::button>

                    <x-filament::button wire:click="saveMqttCredentials" color="success">
                        Save Credentials
                    </x-filament::button>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    For per-device protocol changes, use Device Types and Device resources in IoT Management.
                </p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
