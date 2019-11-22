<?php

/*
 * This file is part of the FiveLab AmqpBundle package
 *
 * (c) FiveLab
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

declare(strict_types = 1);

namespace FiveLab\Bundle\AmqpBundle\DependencyInjection;

use FiveLab\Component\Amqp\Exchange\Definition\Arguments\AlternateExchangeArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\DeadLetterExchangeArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\DeadLetterRoutingKeyArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\ExpiresArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\MaxLengthArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\MaxLengthBytesArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\MaxPriorityArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\MessageTtlArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\OverflowArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\QueueMasterLocatorArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\QueueModeArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\QueueTypeArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\SingleActiveCustomerArgument;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * The extension for configure the AMQP library with you application.
 */
class AmqpExtension extends Extension
{
    /**
     * The list of available connections.
     *
     * @var array
     */
    private $connectionFactories = [];

    /**
     * The list of available channels.
     *
     * @var array
     */
    private $channelFactories = [];

    /**
     * The list of available exchange factories
     *
     * @var array
     */
    private $exchangeFactories = [];

    /**
     * The list of available queue factories
     *
     * @var array
     */
    private $queueFactories = [];

    /**
     * The list of available consumers
     *
     * @var array
     */
    private $consumers = [];

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('factories.xml');
        $loader->load('definitions.xml');
        $loader->load('consumers.xml');
        $loader->load('services.xml');

        if ('php_extension' === $config['driver']) {
            $loader->load('driver/php-extension.xml');
        }

        $this->configureConnections($container, $config['connections']);
        $this->configureExchanges($container, $config['exchanges']);
        $this->configureQueues($container, $config['queues']);
        $this->configureConsumers($container, $config['consumers'], $config['consumer_middleware']);

        $container->getDefinition('fivelab.amqp.console_command.initialize_exchanges')
            ->replaceArgument(1, \array_keys($this->exchangeFactories));

        $container->getDefinition('fivelab.amqp.console_command.initialize_queues')
            ->replaceArgument(1, \array_keys($this->queueFactories));

        if ($config['round_robin']['enable']) {
            $loader->load('round-robin.xml');

            $this->configureRoundRobin($container, $config['round_robin']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'fivelab_amqp';
    }

    /**
     * Configure connections
     *
     * @param ContainerBuilder $container
     * @param array            $connections
     */
    private function configureConnections(ContainerBuilder $container, array $connections): void
    {
        $registryDefinition = $container->getDefinition('fivelab.amqp.connection_factory_registry');

        foreach ($connections as $key => $connection) {
            // Create spool connection service definition
            $originConnectionFactoryServiceId = \sprintf('fivelab.amqp.connection_factory.%s', $key);
            $originConnectionFactoryServiceDefinition = $this->createChildDefinition('fivelab.amqp.spool_connection_factory.abstract');

            $spoolConnections = [];

            // Create connection service definition
            foreach ($connection['host'] as $connectionIndex => $host) {
                $connectionFactoryServiceId = \sprintf('fivelab.amqp.connection_factory.%s_%s', $key, $connectionIndex);
                $connectionFactoryServiceDefinition = $this->createChildDefinition('fivelab.amqp.connection_factory.abstract');

                $connectionFactoryServiceDefinition->replaceArgument(0, [
                    'host'         => $host,
                    'port'         => $connection['port'],
                    'vhost'        => $connection['vhost'],
                    'login'        => $connection['login'],
                    'password'     => $connection['password'],
                    'read_timeout' => $connection['read_timeout'],
                ]);

                $container->setDefinition($connectionFactoryServiceId, $connectionFactoryServiceDefinition);

                $spoolConnections[] = new Reference($connectionFactoryServiceId);
            }

            $originConnectionFactoryServiceDefinition->setArguments($spoolConnections);
            $container->setDefinition($originConnectionFactoryServiceId, $originConnectionFactoryServiceDefinition);

            $this->connectionFactories[$key] = $originConnectionFactoryServiceId;

            $registryDefinition->addMethodCall('add', [
                $key,
                new Reference($originConnectionFactoryServiceId),
            ]);

            // Create channel definition service definition
            $channelDefinitionServiceId = \sprintf('fivelab.amqp.channel_definition.%s', $key);
            $channelDefinitionServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.channel.abstract');

            $container->setDefinition($channelDefinitionServiceId, $channelDefinitionServiceDefinition);

            // Create channel factory service definition
            $channelFactoryServiceId = \sprintf('fivelab.amqp.channel_factory.%s', $key);
            $channelFactoryServiceDefinition = $this->createChildDefinition('fivelab.amqp.channel_factory.abstract');

            $channelFactoryServiceDefinition
                ->replaceArgument(0, new Reference($originConnectionFactoryServiceId))
                ->replaceArgument(1, new Reference($channelDefinitionServiceId));

            $container->setDefinition($channelFactoryServiceId, $channelFactoryServiceDefinition);

            $this->channelFactories[$key] = $channelFactoryServiceId;
        }

        $container->setParameter('fivelab.amqp.connection_factories', \array_keys($this->connectionFactories));
    }

    /**
     * Configure exchanges
     *
     * @param ContainerBuilder $container
     * @param array            $exchanges
     */
    private function configureExchanges(ContainerBuilder $container, array $exchanges): void
    {
        $registryDefinition = $container->getDefinition('fivelab.amqp.exchange_factory_registry');

        foreach ($exchanges as $key => $exchange) {
            if (!\array_key_exists($exchange['connection'], $this->connectionFactories)) {
                throw new \RuntimeException(\sprintf(
                    'Cannot configure exchange with key "%s". The connection "%s" was not found.',
                    $key,
                    $exchange['connection']
                ));
            }

            // Create exchange arguments
            $argumentCollectionServiceId = null;

            if (\array_key_exists('arguments', $exchange)) {
                $exchangeArguments = $exchange['arguments'];

                $argumentCollectionServiceId = \sprintf('fivelab.amqp.exchange_definition.%s.arguments', $key);
                $argumentCollectionServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.argument_collection.abstract');
                $argumentReferences = [[]];

                if ($exchangeArguments['alternate-exchange']) {
                    $argumentReferences[][] = $this->createArgumentDefinition(
                        $container,
                        \sprintf('fivelab.amqp.exchange_definition.%s.arguments.alternate_exchange', $key),
                        AlternateExchangeArgument::class,
                        $exchangeArguments['alternate-exchange']
                    );
                }

                if (\count($exchangeArguments['custom'])) {
                    $argumentReferences[] = $this->createCustomArgumentDefinitions(
                        $container,
                        \sprintf('fivelab.amqp.exchange_definition.%s.arguments', $key),
                        $exchangeArguments['custom']
                    );
                }

                $argumentReferences = \array_merge(...$argumentReferences);

                $argumentCollectionServiceDefinition->setArguments($argumentReferences);

                $container->setDefinition($argumentCollectionServiceId, $argumentCollectionServiceDefinition);
            }

            // Create exchange definition service definition
            $exchangeDefinitionServiceId = \sprintf('fivelab.amqp.exchange_definition.%s', $key);
            $exchangeDefinitionServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.exchange.abstract');

            $exchangeDefinitionServiceDefinition
                ->replaceArgument(0, $exchange['name'])
                ->replaceArgument(1, $exchange['type'])
                ->replaceArgument(2, (bool) $exchange['durable'])
                ->replaceArgument(3, (bool) $exchange['passive'])
                ->replaceArgument(4, $argumentCollectionServiceId);

            $container->setDefinition($exchangeDefinitionServiceId, $exchangeDefinitionServiceDefinition);

            // Create exchange factory service definition
            $exchangeFactoryServiceId = \sprintf('fivelab.amqp.exchange_factory.%s', $key);
            $exchangeFactoryServiceDefinition = $this->createChildDefinition('fivelab.amqp.exchange_factory.abstract');

            $exchangeFactoryServiceDefinition
                ->replaceArgument(0, new Reference($this->channelFactories[$exchange['connection']]))
                ->replaceArgument(1, new Reference($exchangeDefinitionServiceId));

            $container->setDefinition($exchangeFactoryServiceId, $exchangeFactoryServiceDefinition);

            $this->exchangeFactories[$key] = $exchangeFactoryServiceId;

            $registryDefinition->addMethodCall('add', [
                $key,
                new Reference($exchangeFactoryServiceId),
            ]);
        }
    }

    /**
     * Configure queues
     *
     * @param ContainerBuilder $container
     * @param array            $queues
     */
    private function configureQueues(ContainerBuilder $container, array $queues): void
    {
        $queueRegistry = $container->getDefinition('fivelab.amqp.queue_factory_registry');

        foreach ($queues as $key => $queue) {
            // Configure bindings
            $bindingReferences = [];
            $bindingsServiceId = \sprintf('fivelab.amqp.queue_definition.%s.bindings', $key);
            $bindingsServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.queue_binding_collection');

            foreach ($queue['bindings'] as $binding) {
                $bindingServiceId = \sprintf(
                    'fivelab.amqp.queue_definition.%s.binding.%s_%s',
                    $key,
                    $binding['exchange'],
                    $binding['routing']
                );

                $bindingServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.queue_binding.abstract');
                $bindingServiceDefinition
                    ->replaceArgument(0, $binding['exchange'])
                    ->replaceArgument(1, $binding['routing']);

                $container->setDefinition($bindingServiceId, $bindingServiceDefinition);

                $bindingReferences[] = new Reference($bindingServiceId);
            }

            $bindingsServiceDefinition->setArguments($bindingReferences);
            $container->setDefinition($bindingsServiceId, $bindingsServiceDefinition);

            $unbingingReferences = [];
            $unbindingsServiceId = \sprintf('fivelab.amqp.queue_definition.%s.unbindings', $key);
            $unbindingsServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.queue_binding_collection');

            foreach ($queue['unbindings'] as $unbinding) {
                $unbindingServiceId = \sprintf(
                    'fivelab.amqp.queue_definition.%s.unbinding.%s_%s',
                    $key,
                    $unbinding['exchange'],
                    $unbinding['routing']
                );

                $unbindingServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.queue_binding.abstract');
                $unbindingServiceDefinition
                    ->replaceArgument(0, $unbinding['exchange'])
                    ->replaceArgument(1, $unbinding['routing']);

                $container->setDefinition($unbindingServiceId, $unbindingServiceDefinition);

                $unbingingReferences[] = new Reference($unbindingServiceId);
            }

            $unbindingsServiceDefinition->setArguments($unbingingReferences);
            $container->setDefinition($unbindingsServiceId, $unbindingsServiceDefinition);

            // Create argument collection
            $argumentCollectionServiceId = null;

            if (\array_key_exists('arguments', $queue)) {
                $queueArguments = $queue['arguments'];

                $argumentCollectionServiceId = \sprintf('fivelab.amqp.queue_definition.%s.arguments', $key);
                $argumentCollectionServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.argument_collection.abstract');
                $argumentReferences = [[]];

                $possibleArguments = [
                    'dead-letter-exchange' => DeadLetterExchangeArgument::class,
                    'dead-letter-routing-key' => DeadLetterRoutingKeyArgument::class,
                    'expires' => ExpiresArgument::class,
                    'max-length' => MaxLengthArgument::class,
                    'max-length-bytes' => MaxLengthBytesArgument::class,
                    'max-priority' => MaxPriorityArgument::class,
                    'message-ttl' => MessageTtlArgument::class,
                    'overflow' => OverflowArgument::class,
                    'queue-master-locator' => QueueMasterLocatorArgument::class,
                    'queue-mode' => QueueModeArgument::class,
                    'queue-type' => QueueTypeArgument::class,
                ];

                foreach ($possibleArguments as $argumentKey => $argumentClass) {
                    if ($queueArguments[$argumentKey]) {
                        $argumentReferences[][] = $this->createArgumentDefinition(
                            $container,
                            \sprintf('fivelab.amqp.queue_definition.%s.arguments.%s', $key, \str_replace('-', '_', $argumentKey)),
                            $argumentClass,
                            $queueArguments[$argumentKey]
                        );
                    }
                }

                if ($queueArguments['single-active-consumer']) {
                    $argumentReferences[][] = $this->createArgumentDefinition(
                        $container,
                        \sprintf('fivelab.amqp.queue_definition.%s.arguments.single_active_consumer', $key),
                        SingleActiveCustomerArgument::class
                    );
                }

                if (\count($queueArguments['custom'])) {
                    $argumentReferences[] = $this->createCustomArgumentDefinitions(
                        $container,
                        \sprintf('fivelab.amqp.queue_definition.%s.arguments', $key),
                        $queueArguments['custom']
                    );
                }

                $argumentReferences = \array_merge(...$argumentReferences);

                $argumentCollectionServiceDefinition->setArguments($argumentReferences);

                $container->setDefinition($argumentCollectionServiceId, $argumentCollectionServiceDefinition);
            }

            // Configure queue definition service definition
            $queueDefinitionServiceId = \sprintf('fivelab.amqp.queue_definition.%s', $key);
            $queueDefinitionServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.queue.abstract');

            $queueDefinitionServiceDefinition
                ->replaceArgument(0, $queue['name'])
                ->replaceArgument(1, $bindingsServiceId)
                ->replaceArgument(2, $unbindingsServiceId)
                ->replaceArgument(3, (bool) $queue['durable'])
                ->replaceArgument(4, (bool) $queue['passive'])
                ->replaceArgument(5, (bool) $queue['exclusive'])
                ->replaceArgument(6, (bool) $queue['auto_delete'])
                ->replaceArgument(7, new Reference($argumentCollectionServiceId));

            $container->setDefinition($queueDefinitionServiceId, $queueDefinitionServiceDefinition);

            // Configure queue factory service definition
            $queueFactoryServiceId = \sprintf('fivelab.amqp.queue_factory.%s', $key);
            $queueFactoryServiceDefinition = $this->createChildDefinition('fivelab.amqp.queue_factory.abstract');

            $queueFactoryServiceDefinition
                ->replaceArgument(0, new Reference($this->channelFactories[$queue['connection']]))
                ->replaceArgument(1, new Reference($queueDefinitionServiceId));

            $container->setDefinition($queueFactoryServiceId, $queueFactoryServiceDefinition);

            $this->queueFactories[$key] = $queueFactoryServiceId;

            $queueRegistry->addMethodCall('add', [
                $key,
                new Reference($queueFactoryServiceId),
            ]);
        }
    }

    /**
     * Configure consumers
     *
     * @param ContainerBuilder $container
     * @param array            $consumers
     * @param array            $globalMiddlewares
     */
    private function configureConsumers(ContainerBuilder $container, array $consumers, array $globalMiddlewares): void
    {
        $consumerRegistryDefinition = $container->getDefinition('fivelab.amqp.consumer_registry');

        foreach ($consumers as $key => $consumer) {
            if (!\array_key_exists($consumer['queue'], $this->queueFactories)) {
                throw new \InvalidArgumentException(\sprintf(
                    'The consumer "%s" try to use "%s" queue but it queue was not declared.',
                    $key,
                    $consumer['queue']
                ));
            }

            $queueFactoryServiceId = $this->queueFactories[$consumer['queue']];

            // Configure middleware for consumer
            $consumerMiddlewares = \array_merge($globalMiddlewares, $consumer['middleware']);

            $middlewareList = \array_map(function (string $serviceId) {
                return new Reference($serviceId);
            }, $consumerMiddlewares);

            $middlewareServiceId = \sprintf('fivelab.amqp.consumer.%s.middlewares', $key);
            $middlewareServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer.middleware_collection.abstract');
            $middlewareServiceDefinition->setArguments($middlewareList);

            $container->setDefinition($middlewareServiceId, $middlewareServiceDefinition);

            // Configure message handler for consumer
            $messageHandlerChainServiceId = \sprintf('fivelab.amqp.consumer.%s.message_handler', $key);
            $messageHandlerChainServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer.message_handler.abstract');

            $index = 0;

            foreach ($consumer['message_handlers'] as $messageHandler) {
                $messageHandlerChainServiceDefinition->setArgument($index++, new Reference($messageHandler));
            }

            $container->setDefinition($messageHandlerChainServiceId, $messageHandlerChainServiceDefinition);

            // Configure consumer
            $consumerServiceId = \sprintf('fivelab.amqp.consumer.%s', $key);

            if ('single' === $consumer['mode']) {
                // Configure single consumer
                $consumerConfigurationServiceId = \sprintf('fivelab.amqp.consumer.%s.configuration', $key);
                $consumerConfigurationServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer_single.configuration.abstract');

                $consumerConfigurationServiceDefinition
                    ->replaceArgument(0, $consumer['options']['requeue_on_error']);

                $consumerServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer_single.abstract');

                $consumerServiceDefinition
                    ->replaceArgument(0, new Reference($queueFactoryServiceId))
                    ->replaceArgument(1, new Reference($messageHandlerChainServiceId))
                    ->replaceArgument(2, new Reference($middlewareServiceId))
                    ->replaceArgument(3, new Reference($consumerConfigurationServiceId));
            } else if ('spool' === $consumer['mode']) {
                // Configure spool consumer
                $consumerConfigurationServiceId = \sprintf('fivelab.amqp.consumer.%s.configuration', $key);
                $consumerConfigurationServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer_spool.configuration.abstract');

                $consumerConfigurationServiceDefinition
                    ->replaceArgument(0, $consumer['options']['count_messages'])
                    ->replaceArgument(1, $consumer['options']['timeout'])
                    ->replaceArgument(2, $consumer['options']['read_timeout'])
                    ->replaceArgument(3, $consumer['options']['requeue_on_error']);

                $consumerServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer_spool.abstract');

                $consumerServiceDefinition
                    ->replaceArgument(0, new Reference($queueFactoryServiceId))
                    ->replaceArgument(1, new Reference($messageHandlerChainServiceId))
                    ->replaceArgument(2, new Reference($middlewareServiceId))
                    ->replaceArgument(3, new Reference($consumerConfigurationServiceId));
            } else if ('loop' === $consumer['mode']) {
                $consumerConfigurationServiceId = \sprintf('fivelab.amqp.consumer.%s.configuration', $key);
                $consumerConfigurationServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer_loop.configuration.abstract');

                $consumerConfigurationServiceDefinition
                    ->replaceArgument(0, $consumer['options']['read_timeout'])
                    ->replaceArgument(1, $consumer['options']['requeue_on_error']);

                $consumerServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer_loop.abstract');

                $consumerServiceDefinition
                    ->replaceArgument(0, new Reference($queueFactoryServiceId))
                    ->replaceArgument(1, new Reference($messageHandlerChainServiceId))
                    ->replaceArgument(2, new Reference($middlewareServiceId))
                    ->replaceArgument(3, new Reference($consumerConfigurationServiceId));
            } else {
                throw new \InvalidArgumentException(\sprintf(
                    'Unknown mode "%s".',
                    $consumer['mode']
                ));
            }

            $container->setDefinition($consumerConfigurationServiceId, $consumerConfigurationServiceDefinition);
            $container->setDefinition($consumerServiceId, $consumerServiceDefinition);

            $consumerRegistryDefinition->addMethodCall('add', [
                $key,
                new Reference($consumerServiceId),
            ]);

            $this->consumers[$key] = new Reference($consumerServiceId);
        }
    }

    /**
     * Configure round robin
     *
     * @param ContainerBuilder $container
     * @param array            $config
     */
    private function configureRoundRobin(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition('fivelab.amqp.round_robin_consumer.configuration')
            ->replaceArgument(0, $config['executes_messages_per_consumer'])
            ->replaceArgument(1, $config['consumers_read_timeout'])
            ->replaceArgument(2, 0);

        $roundRobinArguments = \array_merge([
            new Reference('fivelab.amqp.round_robin_consumer.configuration'),
        ], \array_values($this->consumers));

        $container->getDefinition('fivelab.amqp.round_robin_consumer')
            ->setArguments($roundRobinArguments);
    }

    /**
     * Create argument definition
     *
     * @param ContainerBuilder $container
     * @param string           $serviceId
     * @param string           $class
     * @param mixed            ...$values
     *
     * @return Reference
     */
    private function createArgumentDefinition(ContainerBuilder $container, string $serviceId, string $class, ...$values): Reference
    {
        $definition = new Definition($class);
        $definition->setArguments($values);

        $container->setDefinition($serviceId, $definition);

        return new Reference($serviceId);
    }

    /**
     * Create argument definition
     *
     * @param ContainerBuilder $container
     * @param string           $serviceId
     * @param string           $name
     * @param mixed            $value
     *
     * @return Reference
     */
    private function createCustomArgumentDefinition(ContainerBuilder $container, string $serviceId, string $name, $value): Reference
    {
        $definition = $this->createChildDefinition('fivelab.amqp.definition.argument.abstract');

        $definition->setArguments([$name, $value]);

        $container->setDefinition($serviceId, $definition);

        return new Reference($serviceId);
    }

    /**
     * Create argument definitions
     *
     * @param ContainerBuilder $container
     * @param string           $serviceIdPrefix
     * @param array            $arguments
     *
     * @return array|Reference[]
     */
    private function createCustomArgumentDefinitions(ContainerBuilder $container, string $serviceIdPrefix, array $arguments): array
    {
        $references = [];

        foreach ($arguments as $name => $value) {
            $serviceId = \sprintf('%s.%s', $serviceIdPrefix, \str_replace('-', '_', $name));

            $references[] = $this->createCustomArgumentDefinition($container, $serviceId, $name, $value);
        }

        return $references;
    }

    /**
     * Create child definition
     *
     * @param string $parentId
     *
     * @return Definition
     */
    private function createChildDefinition(string $parentId): Definition
    {
        if (\class_exists(ChildDefinition::class)) {
            return new ChildDefinition($parentId);
        }

        return new DefinitionDecorator($parentId);
    }
}
