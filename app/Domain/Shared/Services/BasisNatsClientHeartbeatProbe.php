<?php

declare(strict_types=1);

namespace App\Domain\Shared\Services;

use Basis\Nats\Client;
use Basis\Nats\Connection;
use Basis\Nats\Message\Ping;
use LogicException;
use ReflectionProperty;

final class BasisNatsClientHeartbeatProbe
{
    private const DEFAULT_PROCESS_SLICE_SECONDS = 0.1;

    /**
     * @var array<string, ReflectionProperty>
     */
    private static array $connectionProperties = [];

    public function ping(Client $client, ?float $timeoutSeconds = null): bool
    {
        $connection = $client->connection;

        if (! $connection instanceof Connection) {
            return false;
        }

        $resolvedTimeout = $timeoutSeconds ?? $client->configuration->timeout;
        $deadline = microtime(true) + max(0.1, $resolvedTimeout);

        $connection->sendMessage(new Ping);

        $pingAt = $this->readConnectionFloat($connection, 'pingAt');

        while (microtime(true) < $deadline) {
            if ($this->readConnectionFloat($connection, 'pongAt') >= $pingAt) {
                return true;
            }

            $remainingSeconds = $deadline - microtime(true);

            if ($remainingSeconds <= 0) {
                break;
            }

            try {
                $client->process(min(self::DEFAULT_PROCESS_SLICE_SECONDS, $remainingSeconds));
            } catch (LogicException $exception) {
                if (! str_contains($exception->getMessage(), 'No handler')) {
                    throw $exception;
                }
            }
        }

        return $this->readConnectionFloat($connection, 'pongAt') >= $pingAt;
    }

    private function readConnectionFloat(Connection $connection, string $property): float
    {
        $reflection = self::$connectionProperties[$property] ??= $this->reflectConnectionProperty($property);
        $value = $reflection->getValue($connection);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function reflectConnectionProperty(string $property): ReflectionProperty
    {
        $reflection = new ReflectionProperty(Connection::class, $property);
        $reflection->setAccessible(true);

        return $reflection;
    }
}
