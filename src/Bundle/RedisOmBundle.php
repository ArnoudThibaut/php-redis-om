<?php

declare(strict_types=1);

namespace Talleu\RedisOm\Bundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class RedisOmBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('host')
                    ->defaultValue('localhost')
                ->end()
                ->integerNode('port')
                    ->defaultValue(6379)
                ->end()
                ->scalarNode('user')
                    ->defaultNull()
                ->end()
                ->scalarNode('password')
                    ->defaultNull()
                ->end()
                ->booleanNode('use_predis')
                    ->defaultFalse()
                ->end()
                ->booleanNode('persistent_connection')
                    ->defaultFalse()
                ->end()
                ->integerNode('timeout')
                    ->defaultValue(0)
                ->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('./Resources/config/services.php');

        $container->services()
            ->get('php_redis_om.redis_client')
            ->arg(0, $config['host'])
            ->arg(1, $config['port'])
            ->arg(2, $config['user'])
            ->arg(3, $config['password'])
            ->arg(4, $config['use_predis'])
            ->arg(5, $config['persistent_connection'])
            ->arg(6, $config['timeout'])
        ;
    }
    
    public function registerCommands(Application $application)
    {
        parent::registerCommands($application); // TODO: Change the autogenerated stub
    }
}
