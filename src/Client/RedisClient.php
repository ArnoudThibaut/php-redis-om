<?php

declare(strict_types=1);

namespace Talleu\RedisOm\Client;

use Talleu\RedisOm\Exception\BadPropertyConfigurationException;
use Talleu\RedisOm\Exception\RedisClientResponseException;
use Talleu\RedisOm\Om\Mapping\Property;
use Talleu\RedisOm\Om\RedisFormat;

class RedisClient implements RedisClientInterface
{
    public function __construct(protected ?\Redis $redis = null)
    {
        $this->redis = $redis ?? new \Redis(array_key_exists('REDIS_HOST', $_SERVER) ? ['host' => $_SERVER['REDIS_HOST']] : null);
    }

    public function hMSet(string $key, array $data): void
    {
        $result = $this->redis->hMSet(RedisClient::convertPrefix($key), $data);

        if (!$result) {
            $this->handleError(__METHOD__, $this->redis->getLastError());
        }
    }

    public function hGetAll(string $key): array
    {
        $result = $this->redis->hGetAll(RedisClient::convertPrefix($key));

        if ($result === false) {
            $this->handleError(__METHOD__, $this->redis->getLastError());
        }

        return $result;
    }

    public function del(string $key): void
    {
        $result = $this->redis->del(RedisClient::convertPrefix($key));
        if (!$result) {
            $this->handleError(__METHOD__, $this->redis->getLastError());
        }
    }

    public function jsonGet(string $key): ?string
    {
        $result = $this->redis->rawCommand(RedisCommands::JSON_GET->value, static::convertPrefix($key));

        if ($result === false) {
            $this->handleError(RedisCommands::JSON_GET->value, $this->redis->getLastError());
        }

        return $result;
    }

    public function jsonSet(string $key, ?string $path = '$', ?string $value = '{}'): ?bool
    {
        $result = $this->redis->rawCommand(RedisCommands::JSON_SET->value, static::convertPrefix($key), $path, $value);
        if (!$result) {
            $this->handleError(RedisCommands::JSON_SET->value, $this->redis->getLastError());
        }

        return true;
    }

    public function jsonDel(string $key, ?string $path = '$'): ?bool
    {
        return $this->redis->rawCommand(RedisCommands::JSON_DELETE->value, static::convertPrefix($key), $path);
    }

    /**
     * @param \ReflectionProperty[] $properties
     */
    public function createIndex(string $prefixKey, ?string $format = 'HASH', ?array $properties = []): bool
    {
        $prefixKey = static::convertPrefix($prefixKey);

        $arguments = [
            RedisCommands::CREATE_INDEX->value,
            $prefixKey,
            'ON',
            $format,
        ];

        if ($format === RedisFormat::HASH->value) {
            $arguments[] = 'PREFIX';
            $arguments[] = '1';
            $arguments[] = $prefixKey . ':';
        }

        $arguments[] = 'SCHEMA';

        foreach ($properties as $reflectionProperty) {
            if (($propertyAttribute = $reflectionProperty->getAttributes(Property::class)) === []) {
                continue;
            }

            /** @var Property $property */
            $property = $propertyAttribute[0]->newInstance();

            /** @var \ReflectionNamedType|null $propertyType */
            $propertyType = $reflectionProperty->getType();
            if (!in_array($propertyType?->getName(), ['int', 'string', 'float', 'bool'])) {
                continue;
            }

            $type = ($reflectionProperty->getType() === 'int' || $reflectionProperty->getType() === 'float') ? Property::NUMERIC_TYPE : Property::TEXT_TYPE;

            $arguments[] = ($format === RedisFormat::JSON->value ? '$.' : '') . ($property->name !== null ? $property->name : $reflectionProperty->name);
            $arguments[] = 'AS';
            $arguments[] = $property->name ?? $reflectionProperty->name;
            $arguments[] = $type;
            $arguments[] = 'SORTABLE';
        }

        if (array_key_last($arguments) === 'SCHEMA') {
            throw new BadPropertyConfigurationException(sprintf("Your class %s does not have any typed property", $prefixKey));
        }

        /** @var bool $rawResult */
        $rawResult = call_user_func_array([$this->redis, 'rawCommand'], $arguments);

        return $rawResult;
    }

    public function dropIndex(string $prefixKey): bool
    {
        try {
            $key = static::convertPrefix($prefixKey);
            $this->redis->rawCommand(RedisCommands::DROP_INDEX->value, $key);
        } catch (\RedisException $e) {
            return false;
        }

        return true;
    }

    public function count(string $prefixKey, array $criterias = []): int
    {
        $arguments = [RedisCommands::SEARCH->value, static::convertPrefix($prefixKey)];

        foreach ($criterias as $property => $value) {
            $arguments[] = sprintf("@%s:%s", $property, $value);
        }

        $rawResult = call_user_func_array([$this->redis, 'rawCommand'], $arguments);

        return (int) $rawResult[0];
    }

    public function scanKeys(string $prefixKey): array
    {
        $keys = [];
        $iterator = null;
        while($iterator !== 0) {
            $scans = $this->redis->scan($iterator, sprintf("%s*", static::convertPrefix($prefixKey)));
            foreach($scans as $scan) {
                $keys[] = $scan;
            }
        }

        return $keys;
    }

    public function flushAll(): void
    {
        $result = $this->redis->flushAll();
        if (!$result) {
            $this->handleError(__METHOD__, $this->redis->getLastError());
        }
    }

    public function keys(string $pattern): array
    {
        return $this->redis->keys($pattern);
    }

    public function search(string $prefixKey, array $search, array $orderBy, ?string $format = RedisFormat::HASH->value, ?int $numberOfResults = null): array
    {
        $arguments = [RedisCommands::SEARCH->value, static::convertPrefix($prefixKey)];

        if ($search === []) {
            $arguments[] = '*';
        } else {
            $criteria = '';
            foreach ($search as $property => $value) {
                $criteria .= sprintf("@%s:%s ", $property, $value);
            }

            $arguments[] = $criteria;
        }

        foreach ($orderBy as $property => $direction) {
            $arguments[] = 'SORTBY';
            $arguments[] = $property;
            $arguments[] = $direction;
        }

        try {
            $result = call_user_func_array([$this->redis, 'rawCommand'], $arguments);
        } catch (\RedisException $e) {
            $this->handleError(RedisCommands::SEARCH->value, $e->getMessage());
        }

        if (isset($result) && $result === false) {
            $this->handleError(RedisCommands::SEARCH->value, $this->redis->getLastError());
        }

        if ($result[0] === 0) {
            return [];
        }

        $entities = [];
        foreach ($result as $key => $redisData) {
            if ($key > 0 && $key % 2 == 0) {

                if ($format === RedisFormat::JSON->value) {
                    foreach ($redisData as $data) {
                        if (!str_starts_with($data, '{')) {
                            continue;
                        }
                        $entities[] = json_decode($data, true);
                        break;
                    }

                    continue;
                } else {
                    $data = [];
                    for ($i = 0; $i < count($redisData); $i += 2) {
                        $property = $redisData[$i];
                        $value = $redisData[$i + 1];
                        $data[$property] = $value;
                    }
                }

                $entities[] = $data;

                if (count($entities) === $numberOfResults) {
                    return $entities;
                }
            }
        }

        return $entities;
    }

    public static function convertPrefix(string $key): string
    {
        return str_replace('\\', '_', $key);
    }

    private function handleError(string $command, ?string $errorMessage = 'Unknown error'): void
    {
        throw new RedisClientResponseException(
            sprintf("something was wrong when executing %s command, reason: %s", $command, $errorMessage)
        );
    }
}
