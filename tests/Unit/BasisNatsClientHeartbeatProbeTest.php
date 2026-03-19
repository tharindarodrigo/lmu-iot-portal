<?php

declare(strict_types=1);

use App\Domain\Shared\Services\BasisNatsClientHeartbeatProbe;
use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Connection;
use Basis\Nats\Message\Ping;
use Basis\Nats\Message\Prototype;

it('waits for pong responses while continuing to pump the client loop', function (): void {
    $connection = new class extends Connection
    {
        /**
         * @var list<class-string>
         */
        public array $sentMessages = [];

        public function __construct() {}

        public function sendMessage(Prototype $message)
        {
            $this->sentMessages[] = $message::class;

            if ($message instanceof Ping) {
                setConnectionTimestamp($this, 'pingAt', microtime(true));
            }
        }
    };

    $client = new class($connection) extends Client
    {
        public int $processCalls = 0;

        /**
         * @var list<callable(self): void>
         */
        public array $processHooks = [];

        public function __construct(Connection $connection)
        {
            parent::__construct(new Configuration(timeout: 0.1), connection: $connection);
        }

        public function process(null|int|float $timeout = 0, bool $reply = true): mixed
        {
            $this->processCalls++;

            $hook = array_shift($this->processHooks);

            if (is_callable($hook)) {
                $hook($this);
            }

            return null;
        }
    };

    $client->processHooks[] = function () use ($connection): void {
        setConnectionTimestamp($connection, 'pongAt', getConnectionTimestamp($connection, 'pingAt'));
    };

    $probe = new BasisNatsClientHeartbeatProbe;

    expect($probe->ping($client, timeoutSeconds: 0.1))->toBeTrue()
        ->and($client->processCalls)->toBeGreaterThan(0)
        ->and($connection->sentMessages)->toContain(Ping::class);
});

it('ignores no handler loop exceptions while waiting for pong responses', function (): void {
    $connection = new class extends Connection
    {
        public function __construct() {}

        public function sendMessage(Prototype $message)
        {
            if ($message instanceof Ping) {
                setConnectionTimestamp($this, 'pingAt', microtime(true));
            }
        }
    };

    $client = new class($connection) extends Client
    {
        /**
         * @var list<callable(self): void>
         */
        public array $processHooks = [];

        public function __construct(Connection $connection)
        {
            parent::__construct(new Configuration(timeout: 0.1), connection: $connection);
        }

        public function process(null|int|float $timeout = 0, bool $reply = true): mixed
        {
            $hook = array_shift($this->processHooks);

            if (is_callable($hook)) {
                $hook($this);
            }

            return null;
        }
    };

    $client->processHooks[] = function (): void {
        throw new LogicException('No handler for message sid');
    };
    $client->processHooks[] = function () use ($connection): void {
        setConnectionTimestamp($connection, 'pongAt', getConnectionTimestamp($connection, 'pingAt'));
    };

    $probe = new BasisNatsClientHeartbeatProbe;

    expect($probe->ping($client, timeoutSeconds: 0.1))->toBeTrue();
});

it('returns false when a pong is not received before the timeout', function (): void {
    $connection = new class extends Connection
    {
        public function __construct() {}

        public function sendMessage(Prototype $message)
        {
            if ($message instanceof Ping) {
                setConnectionTimestamp($this, 'pingAt', microtime(true));
            }
        }
    };

    $client = new class($connection) extends Client
    {
        public function __construct(Connection $connection)
        {
            parent::__construct(new Configuration(timeout: 0.1), connection: $connection);
        }

        public function process(null|int|float $timeout = 0, bool $reply = true): mixed
        {
            return null;
        }
    };

    $probe = new BasisNatsClientHeartbeatProbe;

    expect($probe->ping($client, timeoutSeconds: 0.1))->toBeFalse();
});

function setConnectionTimestamp(Connection $connection, string $property, float $value): void
{
    $reflection = new ReflectionProperty(Connection::class, $property);
    $reflection->setAccessible(true);
    $reflection->setValue($connection, $value);
}

function getConnectionTimestamp(Connection $connection, string $property): float
{
    $reflection = new ReflectionProperty(Connection::class, $property);
    $reflection->setAccessible(true);

    return (float) $reflection->getValue($connection);
}
