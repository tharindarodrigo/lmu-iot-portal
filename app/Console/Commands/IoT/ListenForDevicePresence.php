<?php

declare(strict_types=1);

namespace App\Console\Commands\IoT;

use App\Domain\DeviceManagement\Services\DevicePresenceService;
use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Message\Payload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ListenForDevicePresence extends Command
{
    protected $signature = 'iot:listen-for-device-presence
                            {--host= : NATS broker host}
                            {--port= : NATS broker port}';

    protected $description = 'Listen for device presence (LWT) messages and update connection state';

    public function handle(DevicePresenceService $presenceService): int
    {
        $host = $this->resolveHost();
        $port = $this->resolvePort();
        $subjectPrefix = $this->resolveSubjectPrefix();
        $subjectSuffix = $this->resolveSubjectSuffix();
        $natsSubject = "{$subjectPrefix}.*.{$subjectSuffix}";

        $this->info('Starting device presence listener...');
        $this->info("Connecting to NATS at {$host}:{$port}");
        $this->info("Listening on subject: {$natsSubject}");

        Log::channel('device_control')->info('Device presence listener starting', [
            'host' => $host,
            'port' => $port,
            'subject' => $natsSubject,
        ]);

        $configuration = new Configuration([
            'host' => $host,
            'port' => $port,
        ]);

        $client = new Client($configuration);

        $client->subscribe($natsSubject, function (Payload $payload) use ($presenceService, $subjectPrefix, $subjectSuffix): void {
            $sourceSubject = $payload->subject ?? '';
            $body = trim($payload->body);

            $deviceUuid = $this->extractDeviceUuid($sourceSubject, $subjectPrefix, $subjectSuffix);

            if ($deviceUuid === null) {
                Log::channel('device_control')->debug('Could not extract device UUID from presence subject', [
                    'subject' => $sourceSubject,
                ]);

                return;
            }

            Log::channel('device_control')->info('Presence message received', [
                'subject' => $sourceSubject,
                'device_uuid' => $deviceUuid,
                'state' => $body,
            ]);

            if ($body === 'offline') {
                $presenceService->markOfflineByUuid($deviceUuid);
                $this->line("Device {$deviceUuid} went offline (LWT)");
            } elseif ($body === 'online') {
                $presenceService->markOnlineByUuid($deviceUuid);
                $this->line("Device {$deviceUuid} came online");
            } else {
                Log::channel('device_control')->warning('Unknown presence payload', [
                    'device_uuid' => $deviceUuid,
                    'body' => $body,
                ]);
            }
        });

        $this->info('Device presence listener is running. Press Ctrl+C to stop.');
        $this->newLine();

        while (true) { /** @phpstan-ignore while.alwaysTrue */
            try {
                $client->process(1);
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'No handler')) {
                    continue;
                }

                Log::channel('device_control')->warning('Presence listener process loop error', [
                    'error' => $e->getMessage(),
                ]);

                sleep(1);
            }
        }

        /** @phpstan-ignore deadCode.unreachable */
        return self::SUCCESS;
    }

    private function extractDeviceUuid(string $subject, string $prefix, string $suffix): ?string
    {
        $natsPrefix = str_replace('/', '.', $prefix);
        $natsSuffix = str_replace('/', '.', $suffix);

        $pattern = '/^'.preg_quote($natsPrefix, '/').'\.([^.]+)\.'.preg_quote($natsSuffix, '/').'$/';

        if (preg_match($pattern, $subject, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function resolveHost(): string
    {
        $hostOption = $this->option('host');

        if (is_string($hostOption) && trim($hostOption) !== '') {
            return trim($hostOption);
        }

        $host = config('iot.nats.host', '127.0.0.1');

        return is_string($host) && trim($host) !== '' ? trim($host) : '127.0.0.1';
    }

    private function resolvePort(): int
    {
        $portOption = $this->option('port');

        if (is_numeric($portOption)) {
            return (int) $portOption;
        }

        $port = config('iot.nats.port', 4223);

        return is_numeric($port) ? (int) $port : 4223;
    }

    private function resolveSubjectPrefix(): string
    {
        $prefix = config('iot.presence.subject_prefix', 'devices');

        return is_string($prefix) && trim($prefix) !== '' ? str_replace('/', '.', trim($prefix)) : 'devices';
    }

    private function resolveSubjectSuffix(): string
    {
        $suffix = config('iot.presence.subject_suffix', 'presence');

        return is_string($suffix) && trim($suffix) !== '' ? str_replace('/', '.', trim($suffix)) : 'presence';
    }
}
