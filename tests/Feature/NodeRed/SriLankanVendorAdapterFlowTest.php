<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('normalizes egravity vendor payloads for flow 1', function (array $message, array $expected): void {
    $result = runSriLankanVendorAdapter($message);

    expect($result[0]['statusCode'])->toBe($expected['status_code'])
        ->and($result[0]['payload'])->toMatchArray($expected['response_payload']);

    if (array_key_exists('mqtt', $expected)) {
        expect($result[2])->not->toBeNull()
            ->and($result[2]['topic'])->toBe($expected['mqtt']['topic']);

        $expectedPayload = $expected['mqtt']['payload'];
        $expectedMeta = is_array($expectedPayload['_meta'] ?? null) ? $expectedPayload['_meta'] : null;

        if ($expectedMeta !== null) {
            unset($expectedPayload['_meta']);
        }

        expect($result[2]['payload'])->toMatchArray($expectedPayload);

        if ($expectedMeta !== null) {
            expect($result[2]['payload']['_meta'])->toMatchArray($expectedMeta);
        }

        return;
    }

    expect($result[2])->toBeNull();
})->with([
    'cld08 multi-metric telemetry' => [
        'message' => [
            'payload' => [
                'external_id' => '00841B48',
                'temp_2' => -3.6,
                'gsm_sl' => -71,
                'batt' => 3.81,
                'pext' => 1,
            ],
            'req' => [
                'query' => [],
                'headers' => [],
                'originalUrl' => '/migration/egravity',
            ],
            'res' => [],
        ],
        'expected' => [
            'status_code' => 202,
            'response_payload' => [
                'accepted' => true,
                'external_id' => '00841B48',
                'topic' => 'migration/source/egravity/00841B48/telemetry',
            ],
            'mqtt' => [
                'topic' => 'migration/source/egravity/00841B48/telemetry',
                'payload' => [
                    'temp_2' => -3.6,
                    'gsm_sl' => -71,
                    'batt' => 3.81,
                    'pext' => 1,
                    '_meta' => [
                        'source_adapter' => 'egravity',
                        'device_external_id' => '00841B48',
                    ],
                ],
            ],
        ],
    ],
    'temperature-only telemetry is keyed by the vendor external_id' => [
        'message' => [
            'payload' => [
                'device_uid' => '009C56ED',
                'temp_1' => 4.2,
                'batt' => 3.64,
            ],
            'req' => [
                'query' => [],
                'headers' => [],
                'originalUrl' => '/migration/egravity',
            ],
            'res' => [],
        ],
        'expected' => [
            'status_code' => 202,
            'response_payload' => [
                'accepted' => true,
                'external_id' => '009C56ED',
                'topic' => 'migration/source/egravity/009C56ED/telemetry',
            ],
            'mqtt' => [
                'topic' => 'migration/source/egravity/009C56ED/telemetry',
                'payload' => [
                    'temp_1' => 4.2,
                    'batt' => 3.64,
                    '_meta' => [
                        'source_adapter' => 'egravity',
                        'device_external_id' => '009C56ED',
                    ],
                ],
            ],
        ],
    ],
    'rejects payloads without an external identifier' => [
        'message' => [
            'payload' => [
                'temp_2' => -1.4,
            ],
            'req' => [
                'query' => [],
                'headers' => [],
                'originalUrl' => '/migration/egravity',
            ],
            'res' => [],
        ],
        'expected' => [
            'status_code' => 422,
            'response_payload' => [
                'accepted' => false,
                'error' => 'missing_external_id',
            ],
        ],
    ],
]);

/**
 * @return array<int, array<string, mixed>|null>
 */
function runSriLankanVendorAdapter(array $message): array
{
    $script = <<<'JS'
const fs = require('fs');

const message = JSON.parse(Buffer.from(process.argv[1], 'base64').toString('utf8'));
const flows = JSON.parse(fs.readFileSync('docker/node-red/data/flows.json', 'utf8'));
const adapterNode = flows.find((node) => node.id === 'sl_vendor_adapter_01');

if (!adapterNode) {
    throw new Error('sl_vendor_adapter_01 node not found.');
}

const adapter = new Function('msg', 'flow', adapterNode.func);
const result = adapter(message, {
    get() {
        return false;
    },
});

process.stdout.write(JSON.stringify(result));
JS;

    $process = new Process([
        'node',
        '-e',
        $script,
        base64_encode(json_encode($message, JSON_THROW_ON_ERROR)),
    ], base_path());
    $process->mustRun();

    /** @var array<int, array<string, mixed>|null> $decoded */
    $decoded = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    return $decoded;
}
