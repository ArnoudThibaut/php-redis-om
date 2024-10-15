<?php

declare(strict_types=1);

namespace Talleu\RedisOm\Client;

class ClientFactory
{
    public function __invoke(string $host, int $port, ?string $user = null, ?string $password = null, bool $usePredis = false, bool $persistentConnection = false, int $timeout = 0): RedisClientInterface
    {
        if ($usePredis) {
            return new PredisClient($host, $port, $user, $password, $persistentConnection, $timeout);
        }

        return new RedisClient($host, $port, $user, $password, $persistentConnection, $timeout);
    }
}
