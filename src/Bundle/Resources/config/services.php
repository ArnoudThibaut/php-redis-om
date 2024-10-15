<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Talleu\RedisOm\Bundle\Command\GenerateSchemaCommand;
use Talleu\RedisOm\Client\ClientFactory;
use Talleu\RedisOm\Client\RedisClientInterface;
use Talleu\RedisOm\Om\RedisObjectManager;
use Talleu\RedisOm\Om\RedisObjectManagerInterface;

return static function (ContainerConfigurator $container) {
  $container->services()
      ->set('php_redis_om.object_manager', RedisObjectManager::class)
        ->arg('$redisClient', service('php_redis_om.redis_client'))
        ->alias(RedisObjectManagerInterface::class, 'php_redis_om.object_manager')
      ->set('php_redis_om.redis_client', RedisClientInterface::class)
        ->factory(service('php_redis_om.redis_client.factory'))
      ->set('php_redis_om.redis_client.factory', ClientFactory::class)
      ->set('php_redis_om.command.generate_schema', GenerateSchemaCommand::class)
        ->arg('$manager', service('php_redis_om.object_manager'))
        ->tag('console.command')
  ;
};
