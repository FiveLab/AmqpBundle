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

use FiveLab\Bundle\AmqpBundle\Factory\DriverFactory;
use FiveLab\Component\Amqp\Channel\ChannelFactoryInterface;
use FiveLab\Component\Amqp\Connection\ConnectionFactoryInterface;
use FiveLab\Component\Amqp\Connection\Dsn;
use FiveLab\Component\Amqp\Exchange\Definition\Arguments\AlternateExchangeArgument;
use FiveLab\Component\Amqp\Exchange\ExchangeFactoryInterface;
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
use FiveLab\Component\Amqp\Queue\QueueFactoryInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class AmqpExtension extends Extension
{
    private array $driverFactories = [];
    private array $connectionFactories = [];
    private array $defaultChannelFactories = [];
    private array $channelFactories = [];
    private array $channelConnections = [];
    private array $exchangeFactories = [];
    private array $exchangeConnections = [];
    private array $queueFactories = [];
    private array $queueConnections = [];
    private array $consumers = [];
    private array $consumerCheckers = [];
    private array $publishers = [];
    private array $savepointPublishers = [];

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);
        $config = $this->setDefaultStrategy($config);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $loader->load('definitions.php');
        $loader->load('consumers.php');
        $loader->load('publishers.php');
        $loader->load('services.php');

        $this->configureConnections($container, $config['connections']);
        $this->configureChannels($container, $config['channels']);
        $this->configureExchanges($container, $config['exchanges']);
        $this->configureQueues($container, $config['queues'], $config['queue_default_arguments'] ?? []);
        $this->configurePublishers($container, $config['publishers'], $config['publisher_middleware']);
        $this->configureConsumers($container, $config['consumers'], $config['consumer_middleware'], $config['consumer_event_handlers'], $config['consumer_defaults']);

        if ($this->isConfigEnabled($container, $config['round_robin'])) {
            $loader->load('round-robin.php');

            $this->configureRoundRobin($container, $config['round_robin']);
        }

        if (\array_key_exists('delay', $config) && $config['delay']) {
            $loader->load('delay.php');

            $this->configureDelay(
                $container,
                $config['delay'],
                $config['publisher_middleware'],
                $config['consumer_middleware'],
                $config['consumer_event_handlers'],
                $config['queue_default_arguments'] ?? [],
                $config['consumer_defaults'] ?? []
            );
        }

        $container->getDefinition('fivelab.amqp.console_command.initialize_exchanges')
            ->replaceArgument(1, \array_keys($this->exchangeFactories));

        $container->getDefinition('fivelab.amqp.console_command.initialize_queues')
            ->replaceArgument(1, \array_keys($this->queueFactories));

        $container->getDefinition('fivelab.amqp.console_command.list_consumers')
            ->replaceArgument(0, \array_keys($this->consumers));
    }

    public function getAlias(): string
    {
        return 'fivelab_amqp';
    }

    private function setDefaultStrategy(array $config): array
    {
        $strategy = $config['consumer_defaults']['strategy'];

        foreach ($config['consumers'] as $key => $consumer) {
            if (!$consumer['strategy']) {
                $config['consumers'][$key]['strategy'] = $strategy;
            }
        }

        if (\array_key_exists('delay', $config) && !$config['delay']['strategy']) {
            $config['delay']['strategy'] = $strategy;
        }

        return $config;
    }

    private function configureConnections(ContainerBuilder $container, array $connections): void
    {
        $registryDef = $container->getDefinition('fivelab.amqp.connection_factory_registry');

        foreach ($connections as $key => $connection) {
            // Create DSN for connection.
            $dsnServiceId = \sprintf('fivelab.amqp.connection_dsn.%s', $key);
            $dsnServiceDef = new Definition(Dsn::class, [$connection['dsn']]);
            $dsnServiceDef->setFactory([Dsn::class, 'fromDsn']);

            $container->setDefinition($dsnServiceId, $dsnServiceDef);

            // Create driver factory
            $driverFactoryServiceId = \sprintf('fivelab.amqp.driver_factory.%s', $key);
            $driverFactoryServiceDef = new Definition(DriverFactory::class, [new Reference($dsnServiceId)]);

            $container->setDefinition($driverFactoryServiceId, $driverFactoryServiceDef);

            $this->driverFactories[$key] = $driverFactoryServiceId;

            // Create connection
            $connectionFactoryServiceId = \sprintf('fivelab.amqp.connection_factory.%s', $key);
            $connectionFactoryServiceDef = new Definition(ConnectionFactoryInterface::class);
            $connectionFactoryServiceDef->setFactory([new Reference($driverFactoryServiceId), 'createConnectionFactory']);

            $container->setDefinition($connectionFactoryServiceId, $connectionFactoryServiceDef);

            $this->connectionFactories[$key] = $connectionFactoryServiceId;

            $registryDef->addMethodCall('add', [
                $key,
                new Reference($connectionFactoryServiceId),
            ]);

            // Create default channel definition service definition
            $channelDefinitionServiceId = \sprintf('fivelab.amqp.channel_definition.%s', $key);
            $channelDefinitionServiceDef = new ChildDefinition('fivelab.amqp.definition.channel.abstract');

            $container->setDefinition($channelDefinitionServiceId, $channelDefinitionServiceDef);

            // Create default channel factory service definition
            $channelFactoryServiceId = \sprintf('fivelab.amqp.channel_factory.%s', $key);
            $channelFactoryServiceDef = new Definition(ChannelFactoryInterface::class);

            $channelFactoryServiceDef
                ->setFactory([new Reference($driverFactoryServiceId), 'createChannelFactory'])
                ->setArguments([new Reference($connectionFactoryServiceId), new Reference($channelDefinitionServiceId)]);

            $container->setDefinition($channelFactoryServiceId, $channelFactoryServiceDef);

            $this->defaultChannelFactories[$key] = $channelFactoryServiceId;
        }

        $container->setParameter('fivelab.amqp.connection_factories', \array_keys($this->connectionFactories));
    }

    private function configureChannels(ContainerBuilder $container, array $channels): void
    {
        foreach ($channels as $key => $channel) {
            $connectionKey = $channel['connection'];

            if (!\array_key_exists($connectionKey, $this->driverFactories)) {
                throw new \RuntimeException(\sprintf(
                    'Can\'t configure channel "%s". Connection "%s" was not found.',
                    $key,
                    $connectionKey
                ));
            }

            $driverFactoryServiceId = $this->driverFactories[$connectionKey];

            $connectionFactoryServiceId = $this->connectionFactories[$connectionKey];

            // Create channel definition
            $channelDefinitionServiceId = \sprintf('fivelab.amqp.channel_definition.%s.%s', $connectionKey, $key);
            $channelDefinitionServiceDef = new ChildDefinition('fivelab.amqp.definition.channel.abstract');

            $container->setDefinition($channelDefinitionServiceId, $channelDefinitionServiceDef);

            $channelFactoryServiceId = \sprintf('fivelab.amqp.channel_factory.%s.%s', $connectionKey, $key);
            $channelFactoryServiceDef = new Definition(ChannelFactoryInterface::class);
            $channelFactoryServiceDef->setFactory([new Reference($driverFactoryServiceId), 'createChannelFactory']);
            $channelFactoryServiceDef->setArguments([new Reference($connectionFactoryServiceId), new Reference($channelDefinitionServiceId)]);

            $container->setDefinition($channelFactoryServiceId, $channelFactoryServiceDef);

            $this->channelFactories[$key] = $channelFactoryServiceId;
            $this->channelConnections[$key] = $connectionKey;
        }
    }

    private function configureExchanges(ContainerBuilder $container, array $exchanges): void
    {
        $registryDef = $container->getDefinition('fivelab.amqp.exchange_factory_registry');

        foreach ($exchanges as $key => $exchange) {
            if (!\array_key_exists($exchange['connection'], $this->driverFactories)) {
                throw new \RuntimeException(\sprintf(
                    'Cannot configure exchange with key "%s". The connection "%s" was not found.',
                    $key,
                    $exchange['connection']
                ));
            }

            // Configure bindings
            $bindingReferences = [];
            $bindingsServiceId = \sprintf('fivelab.amqp.exchange_definition.%s.bindings', $key);
            $bindingsServiceDef = new ChildDefinition('fivelab.amqp.definition.bindings');

            foreach ($exchange['bindings'] as $binding) {
                $bindingServiceId = \sprintf(
                    'fivelab.amqp.exchange_definition.%s.binding.%s_%s',
                    $key,
                    $binding['exchange'],
                    $binding['routing']
                );

                $bindingServiceDef = new ChildDefinition('fivelab.amqp.definition.binding.abstract');
                $bindingServiceDef
                    ->replaceArgument(0, $binding['exchange'])
                    ->replaceArgument(1, $binding['routing']);

                $container->setDefinition($bindingServiceId, $bindingServiceDef);

                $bindingReferences[] = new Reference($bindingServiceId);
            }

            $bindingsServiceDef->setArguments($bindingReferences);
            $container->setDefinition($bindingsServiceId, $bindingsServiceDef);

            $unbingingReferences = [];
            $unbindingsServiceId = \sprintf('fivelab.amqp.exchange_definition.%s.unbindings', $key);
            $unbindingsServiceDef = new ChildDefinition('fivelab.amqp.definition.bindings');

            foreach ($exchange['unbindings'] as $unbinding) {
                $unbindingServiceId = \sprintf(
                    'fivelab.amqp.exchange_definition.%s.unbinding.%s_%s',
                    $key,
                    $unbinding['exchange'],
                    $unbinding['routing']
                );

                $unbindingServiceDef = new ChildDefinition('fivelab.amqp.definition.binding.abstract');
                $unbindingServiceDef
                    ->replaceArgument(0, $unbinding['exchange'])
                    ->replaceArgument(1, $unbinding['routing']);

                $container->setDefinition($unbindingServiceId, $unbindingServiceDef);

                $unbingingReferences[] = new Reference($unbindingServiceId);
            }

            $unbindingsServiceDef->setArguments($unbingingReferences);
            $container->setDefinition($unbindingsServiceId, $unbindingsServiceDef);

            // Create exchange arguments
            $argumentsServiceId = null;

            if (\array_key_exists('arguments', $exchange)) {
                $exchangeArguments = $exchange['arguments'];

                $argumentsServiceId = \sprintf('fivelab.amqp.exchange_definition.%s.arguments', $key);
                $argumentsServiceDef = new ChildDefinition('fivelab.amqp.definition.arguments.abstract');
                $argumentReferences = [[]];

                if (\array_key_exists('alternate-exchange', $exchangeArguments) && $exchangeArguments['alternate-exchange']) {
                    $argumentReferences[][] = $this->createArgumentDefinition(
                        $container,
                        \sprintf('fivelab.amqp.exchange_definition.%s.arguments.alternate_exchange', $key),
                        AlternateExchangeArgument::class,
                        $exchangeArguments['alternate-exchange']
                    );
                }

                if (\array_key_exists('custom', $exchangeArguments) && \count($exchangeArguments['custom'])) {
                    $argumentReferences[] = $this->createCustomArgumentDefinitions(
                        $container,
                        \sprintf('fivelab.amqp.exchange_definition.%s.arguments', $key),
                        $exchangeArguments['custom']
                    );
                }

                $argumentReferences = \array_merge(...$argumentReferences);

                $argumentsServiceDef->setArguments($argumentReferences);

                $container->setDefinition($argumentsServiceId, $argumentsServiceDef);
            }

            // Create exchange definition service definition
            $exchangeDefinitionServiceId = \sprintf('fivelab.amqp.exchange_definition.%s', $key);
            $exchangeDefinitionServiceDef = new ChildDefinition('fivelab.amqp.definition.exchange.abstract');

            $exchangeDefinitionServiceDef
                ->replaceArgument(0, 'amq.default' === $exchange['name'] ? '' : $exchange['name'])
                ->replaceArgument(1, $exchange['type'])
                ->replaceArgument(2, (bool) $exchange['durable'])
                ->replaceArgument(3, self::resolveBoolOrExpression($exchange['passive']))
                ->replaceArgument(4, $argumentsServiceId ? new Reference($argumentsServiceId) : null)
                ->replaceArgument(5, new Reference($bindingsServiceId))
                ->replaceArgument(6, new Reference($unbindingsServiceId));

            $container->setDefinition($exchangeDefinitionServiceId, $exchangeDefinitionServiceDef);

            // Create exchange factory service definition
            $exchangeFactoryServiceId = \sprintf('fivelab.amqp.exchange_factory.%s', $key);
            $exchangeFactoryServiceDef = new Definition(ExchangeFactoryInterface::class);
            $exchangeFactoryServiceDef->setFactory([new Reference($this->driverFactories[$exchange['connection']]), 'createExchangeFactory']);

            $exchangeFactoryServiceDef->setArguments([
                new Reference($this->defaultChannelFactories[$exchange['connection']]),
                new Reference($exchangeDefinitionServiceId),
            ]);

            $container->setDefinition($exchangeFactoryServiceId, $exchangeFactoryServiceDef);

            $this->exchangeFactories[$key] = $exchangeFactoryServiceId;
            $this->exchangeConnections[$key] = $exchange['connection'];

            $registryDef->addMethodCall('add', [
                $key,
                new Reference($exchangeFactoryServiceId),
            ]);
        }

        $container->setParameter('fivelab.amqp.exchange_factories', \array_keys($this->exchangeFactories));
    }

    private function configureQueues(ContainerBuilder $container, array $queues, array $queueDefaultArguments): void
    {
        $queueRegistry = $container->getDefinition('fivelab.amqp.queue_factory_registry');

        foreach ($queues as $key => $queue) {
            // Configure bindings
            $bindingReferences = [];
            $bindingsServiceId = \sprintf('fivelab.amqp.queue_definition.%s.bindings', $key);
            $bindingsServiceDef = new ChildDefinition('fivelab.amqp.definition.bindings');

            foreach ($queue['bindings'] as $binding) {
                $bindingServiceId = \sprintf(
                    'fivelab.amqp.queue_definition.%s.binding.%s_%s',
                    $key,
                    $binding['exchange'],
                    $binding['routing']
                );

                $bindingServiceDef = new ChildDefinition('fivelab.amqp.definition.binding.abstract');
                $bindingServiceDef
                    ->replaceArgument(0, $binding['exchange'])
                    ->replaceArgument(1, $binding['routing']);

                $container->setDefinition($bindingServiceId, $bindingServiceDef);

                $bindingReferences[] = new Reference($bindingServiceId);
            }

            $bindingsServiceDef->setArguments($bindingReferences);
            $container->setDefinition($bindingsServiceId, $bindingsServiceDef);

            $unbingingReferences = [];
            $unbindingsServiceId = \sprintf('fivelab.amqp.queue_definition.%s.unbindings', $key);
            $unbindingsServiceDef = new ChildDefinition('fivelab.amqp.definition.bindings');

            foreach ($queue['unbindings'] as $unbinding) {
                $unbindingServiceId = \sprintf(
                    'fivelab.amqp.queue_definition.%s.unbinding.%s_%s',
                    $key,
                    $unbinding['exchange'],
                    $unbinding['routing']
                );

                $unbindingServiceDef = new ChildDefinition('fivelab.amqp.definition.binding.abstract');
                $unbindingServiceDef
                    ->replaceArgument(0, $unbinding['exchange'])
                    ->replaceArgument(1, $unbinding['routing']);

                $container->setDefinition($unbindingServiceId, $unbindingServiceDef);

                $unbingingReferences[] = new Reference($unbindingServiceId);
            }

            $unbindingsServiceDef->setArguments($unbingingReferences);
            $container->setDefinition($unbindingsServiceId, $unbindingsServiceDef);

            // Create arguments
            $argumentsServiceId = null;

            $queueArguments = $queue['arguments'] ?? [];
            $queueArguments = \array_merge($queueDefaultArguments, $queueArguments);

            if (\count($queueArguments)) {
                $argumentsServiceDef = new ChildDefinition('fivelab.amqp.definition.arguments.abstract');
                $argumentReferences = [[]];

                $possibleArguments = [
                    'dead-letter-exchange'    => DeadLetterExchangeArgument::class,
                    'dead-letter-routing-key' => DeadLetterRoutingKeyArgument::class,
                    'expires'                 => ExpiresArgument::class,
                    'max-length'              => MaxLengthArgument::class,
                    'max-length-bytes'        => MaxLengthBytesArgument::class,
                    'max-priority'            => MaxPriorityArgument::class,
                    'message-ttl'             => MessageTtlArgument::class,
                    'overflow'                => OverflowArgument::class,
                    'queue-master-locator'    => QueueMasterLocatorArgument::class,
                    'queue-mode'              => QueueModeArgument::class,
                    'queue-type'              => QueueTypeArgument::class,
                ];

                foreach ($possibleArguments as $argumentKey => $argumentClass) {
                    if (\array_key_exists($argumentKey, $queueArguments) && $queueArguments[$argumentKey]) {
                        $argumentReferences[][] = $this->createArgumentDefinition(
                            $container,
                            \sprintf('fivelab.amqp.queue_definition.%s.arguments.%s', $key, \str_replace('-', '_', $argumentKey)),
                            $argumentClass,
                            $queueArguments[$argumentKey]
                        );
                    }
                }

                if (\array_key_exists('single-active-consumer', $queueArguments) && $queueArguments['single-active-consumer']) {
                    $argumentReferences[][] = $this->createArgumentDefinition(
                        $container,
                        \sprintf('fivelab.amqp.queue_definition.%s.arguments.single_active_consumer', $key),
                        SingleActiveCustomerArgument::class
                    );
                }

                if (\array_key_exists('custom', $queueArguments) && \count($queueArguments['custom'])) {
                    $argumentReferences[] = $this->createCustomArgumentDefinitions(
                        $container,
                        \sprintf('fivelab.amqp.queue_definition.%s.arguments', $key),
                        $queueArguments['custom']
                    );
                }

                $argumentReferences = \array_merge(...$argumentReferences);

                if (\count($argumentReferences)) {
                    $argumentsServiceId = \sprintf('fivelab.amqp.queue_definition.%s.arguments', $key);
                    $argumentsServiceDef->setArguments($argumentReferences);

                    $container->setDefinition($argumentsServiceId, $argumentsServiceDef);
                }
            }

            // Configure queue definition service definition
            $queueDefinitionServiceId = \sprintf('fivelab.amqp.queue_definition.%s', $key);
            $queueDefinitionServiceDef = new ChildDefinition('fivelab.amqp.definition.queue.abstract');

            $queueDefinitionServiceDef
                ->replaceArgument(0, $queue['name'])
                ->replaceArgument(1, new Reference($bindingsServiceId))
                ->replaceArgument(2, new Reference($unbindingsServiceId))
                ->replaceArgument(3, (bool) $queue['durable'])
                ->replaceArgument(4, self::resolveBoolOrExpression($queue['passive']))
                ->replaceArgument(5, (bool) $queue['exclusive'])
                ->replaceArgument(6, (bool) $queue['auto_delete'])
                ->replaceArgument(7, $argumentsServiceId ? new Reference($argumentsServiceId) : null);

            $container->setDefinition($queueDefinitionServiceId, $queueDefinitionServiceDef);

            // Configure queue factory service definition
            $queueFactoryServiceId = \sprintf('fivelab.amqp.queue_factory.%s', $key);
            $queueFactoryServiceDef = new Definition(QueueFactoryInterface::class);
            $queueFactoryServiceDef->setFactory([new Reference($this->driverFactories[$queue['connection']]), 'createQueueFactory']);

            $queueFactoryServiceDef->setArguments([
                new Reference($this->defaultChannelFactories[$queue['connection']]),
                new Reference($queueDefinitionServiceId),
            ]);

            $container->setDefinition($queueFactoryServiceId, $queueFactoryServiceDef);

            $this->queueFactories[$key] = $queueFactoryServiceId;
            $this->queueConnections[$key] = $queue['connection'];

            $queueRegistry->addMethodCall('add', [
                $key,
                new Reference($queueFactoryServiceId),
            ]);
        }

        $container->setParameter('fivelab.amqp.queue_factories', \array_keys($this->queueFactories));
    }

    private function configureConsumers(ContainerBuilder $container, array $consumers, array $globalMiddlewares, array $eventHandlers, array $defaults): void
    {
        $consumerRegistryDef = $container->getDefinition('fivelab.amqp.consumer_registry');
        $checkConsumerRegistryDef = $container->getDefinition('fivelab.amqp.consumer_checker_registry');

        foreach ($consumers as $key => $consumer) {
            if (!\array_key_exists($consumer['queue'], $this->queueFactories)) {
                throw new \InvalidArgumentException(\sprintf(
                    'The consumer "%s" try to use "%s" queue but it queue was not declared.',
                    $key,
                    $consumer['queue']
                ));
            }

            // Add checker to registry, if configured
            if ($consumer['checker']) {
                $this->consumerCheckers[$key] = new Reference($consumer['checker']);
            }

            if ($consumer['channel']) {
                // Use specific channel. Create new exchange factory for it.
                if (!\array_key_exists($consumer['channel'], $this->channelFactories)) {
                    throw new \RuntimeException(\sprintf(
                        'Can\'t configure consumer "%s". The channel "%s" was not found.',
                        $key,
                        $consumer['channel']
                    ));
                }

                $channelConnectionKey = $this->channelConnections[$consumer['channel']];
                $queueConnectionKey = $this->queueConnections[$consumer['queue']];

                if ($channelConnectionKey !== $queueConnectionKey) {
                    throw new \RuntimeException(\sprintf(
                        'Can\'t configure consumer "%s". Different connections for queue and channel. Queue connection is "%s" and channel connection is "%s".',
                        $key,
                        $queueConnectionKey,
                        $channelConnectionKey
                    ));
                }

                // Create new queue factory with created exchange definition.
                $channelFactoryServiceId = $this->channelFactories[$consumer['channel']];
                $queueDefinitionServiceId = \sprintf('fivelab.amqp.queue_definition.%s', $consumer['queue']);

                $queueFactoryServiceId = \sprintf('fivelab.amqp.queue_factory.%s.%s', $consumer['queue'], $key);
                $queueFactoryServiceDef = new ChildDefinition('fivelab.amqp.queue_factory.abstract');

                $queueFactoryServiceDef
                    ->replaceArgument(0, new Reference($channelFactoryServiceId))
                    ->replaceArgument(1, new Reference($queueDefinitionServiceId));

                $container->setDefinition($queueFactoryServiceId, $queueFactoryServiceDef);
            } else {
                $queueFactoryServiceId = $this->queueFactories[$consumer['queue']];
            }

            // Configure middleware for consumer
            $consumerMiddlewares = \array_merge($globalMiddlewares, $consumer['middleware']);

            $middlewareList = \array_map(static function (string $serviceId) {
                return new Reference($serviceId);
            }, $consumerMiddlewares);

            $middlewareServiceId = \sprintf('fivelab.amqp.consumer.%s.middlewares', $key);
            $middlewareServiceDef = new ChildDefinition('fivelab.amqp.consumer.middlewares.abstract');
            $middlewareServiceDef->setArguments($middlewareList);

            $container->setDefinition($middlewareServiceId, $middlewareServiceDef);

            // Configure message handler for consumer
            $messageHandlersServiceId = \sprintf('fivelab.amqp.consumer.%s.message_handler', $key);
            $messageHandlersServiceDef = new ChildDefinition('fivelab.amqp.consumer.message_handler.abstract');

            $index = 0;

            foreach ($consumer['message_handlers'] as $messageHandler) {
                $messageHandlersServiceDef->setArgument($index++, new Reference($messageHandler));
            }

            $container->setDefinition($messageHandlersServiceId, $messageHandlersServiceDef);

            // Configure strategy
            $strategyServiceId = \sprintf('fivelab.amqp.consumer.%s.strategy', $key);

            if ('consume' === $consumer['strategy']) {
                $strategyServiceDef = new ChildDefinition('fivelab.amqp.consumer.strategy.default.abstract');
            } elseif ('loop' === $consumer['strategy']) {
                $tickHandler = $consumer['tick_handler'] ?? $defaults['tick_handler'];

                $strategyServiceDef = (new ChildDefinition('fivelab.amqp.consumer.strategy.loop.abstract'))
                    ->replaceArgument(0, $consumer['options']['idle_timeout'])
                    ->replaceArgument(1, $tickHandler ? new Reference($tickHandler) : null);
            } else {
                throw new \RuntimeException(\sprintf(
                    'Unknown consume strategy "%s".',
                    $consumer['strategy']
                ));
            }

            $container->setDefinition($strategyServiceId, $strategyServiceDef);

            // Configure consumer
            $consumerServiceId = \sprintf('fivelab.amqp.consumer.%s', $key);
            $tagNameGenerator = null;

            if ($consumer['tag_generator']) {
                $tagNameGenerator = new Reference($consumer['tag_generator']);
            }

            if ('single' === $consumer['mode']) {
                // Configure single consumer
                $consumerConfigurationServiceId = \sprintf('fivelab.amqp.consumer.%s.configuration', $key);
                $consumerConfigurationServiceDef = new ChildDefinition('fivelab.amqp.consumer_single.configuration.abstract');

                $consumerConfigurationServiceDef
                    ->replaceArgument(0, $consumer['options']['requeue_on_error'])
                    ->replaceArgument(1, $consumer['options']['prefetch_count'])
                    ->replaceArgument(2, $tagNameGenerator);

                $consumerServiceDef = new ChildDefinition('fivelab.amqp.consumer_single.abstract');

                $consumerServiceDef
                    ->replaceArgument(0, new Reference($queueFactoryServiceId))
                    ->replaceArgument(1, new Reference($messageHandlersServiceId))
                    ->replaceArgument(2, new Reference($middlewareServiceId))
                    ->replaceArgument(3, new Reference($consumerConfigurationServiceId))
                    ->replaceArgument(4, new Reference($strategyServiceId));
            } elseif ('spool' === $consumer['mode']) {
                // Configure spool consumer
                $consumerConfigurationServiceId = \sprintf('fivelab.amqp.consumer.%s.configuration', $key);
                $consumerConfigurationServiceDef = new ChildDefinition('fivelab.amqp.consumer_spool.configuration.abstract');

                $consumerConfigurationServiceDef
                    ->replaceArgument(0, $consumer['options']['prefetch_count'])
                    ->replaceArgument(1, $consumer['options']['timeout'])
                    ->replaceArgument(2, $consumer['options']['read_timeout'])
                    ->replaceArgument(3, $consumer['options']['requeue_on_error'])
                    ->replaceArgument(4, $tagNameGenerator);

                $consumerServiceDef = new ChildDefinition('fivelab.amqp.consumer_spool.abstract');

                $consumerServiceDef
                    ->replaceArgument(0, new Reference($queueFactoryServiceId))
                    ->replaceArgument(1, new Reference($messageHandlersServiceId))
                    ->replaceArgument(2, new Reference($middlewareServiceId))
                    ->replaceArgument(3, new Reference($consumerConfigurationServiceId))
                    ->replaceArgument(4, new Reference($strategyServiceId));
            } elseif ('loop' === $consumer['mode']) {
                $consumerConfigurationServiceId = \sprintf('fivelab.amqp.consumer.%s.configuration', $key);
                $consumerConfigurationServiceDef = new ChildDefinition('fivelab.amqp.consumer_loop.configuration.abstract');

                $consumerConfigurationServiceDef
                    ->replaceArgument(0, $consumer['options']['read_timeout'])
                    ->replaceArgument(1, $consumer['options']['requeue_on_error'])
                    ->replaceArgument(2, $consumer['options']['prefetch_count'])
                    ->replaceArgument(3, $tagNameGenerator);

                $consumerServiceDef = new ChildDefinition('fivelab.amqp.consumer_loop.abstract');

                $consumerServiceDef
                    ->replaceArgument(0, new Reference($queueFactoryServiceId))
                    ->replaceArgument(1, new Reference($messageHandlersServiceId))
                    ->replaceArgument(2, new Reference($middlewareServiceId))
                    ->replaceArgument(3, new Reference($consumerConfigurationServiceId))
                    ->replaceArgument(4, new Reference($strategyServiceId));
            } else {
                throw new \InvalidArgumentException(\sprintf(
                    'Unknown mode "%s".',
                    $consumer['mode']
                ));
            }

            foreach ($eventHandlers as $eventHandler) {
                $consumerServiceDef->addMethodCall(
                    'addEventHandler',
                    [new ServiceClosureArgument(new Reference($eventHandler)), true]
                );
            }

            $container->setDefinition($consumerConfigurationServiceId, $consumerConfigurationServiceDef);
            $container->setDefinition($consumerServiceId, $consumerServiceDef);

            $this->consumers[$key] = new Reference($consumerServiceId);
        }

        $consumersLocatorRef = ServiceLocatorTagPass::register($container, $this->consumers);
        $consumerRegistryDef->replaceArgument(0, $consumersLocatorRef);

        $checkersLocatorRef = ServiceLocatorTagPass::register($container, $this->consumerCheckers);
        $checkConsumerRegistryDef->replaceArgument(0, $checkersLocatorRef);

        $container->setParameter('fivelab.amqp.consumers', \array_keys($this->consumers));
    }

    private function configurePublishers(ContainerBuilder $container, array $publishers, array $globalMiddlewares): void
    {
        $publishersRegistryDef = $container->getDefinition('fivelab.amqp.publisher_registry');

        foreach ($publishers as $key => $publisher) {
            if (!\array_key_exists($publisher['exchange'], $this->exchangeFactories)) {
                throw new \InvalidArgumentException(\sprintf(
                    'The publisher "%s" try to use "%s" exchange but it exchange was not declared.',
                    $key,
                    $publisher['exchange']
                ));
            }

            if ($publisher['channel']) {
                // Use specific channel. Create new exchange factory for it.
                if (!\array_key_exists($publisher['channel'], $this->channelFactories)) {
                    throw new \RuntimeException(\sprintf(
                        'Can\'t configure publisher "%s". The channel "%s" was not found.',
                        $key,
                        $publisher['channel']
                    ));
                }

                $channelConnectionKey = $this->channelConnections[$publisher['channel']];
                $exchangeConnectionKey = $this->exchangeConnections[$publisher['exchange']];

                if ($channelConnectionKey !== $exchangeConnectionKey) {
                    throw new \RuntimeException(\sprintf(
                        'Can\'t configure publisher "%s". Different connections for exchange and channel. Exchange connection is "%s" and channel connection is "%s".',
                        $key,
                        $exchangeConnectionKey,
                        $channelConnectionKey
                    ));
                }

                $driverFactoryServiceId = $this->driverFactories[$this->channelConnections[$publisher['channel']]];

                // Create new exchange factory with created exchange definition.
                $channelFactoryServiceId = $this->channelFactories[$publisher['channel']];
                $exchangeDefinitionServiceId = \sprintf('fivelab.amqp.exchange_definition.%s', $publisher['exchange']);

                $exchangeFactoryServiceId = \sprintf('fivelab.amqp.exchange_factory.%s.%s', $publisher['exchange'], $key);
                $exchangeFactoryServiceDef = new Definition(ExchangeFactoryInterface::class);
                $exchangeFactoryServiceDef->setFactory([new Reference($driverFactoryServiceId), 'createExchangeFactory']);
                $exchangeFactoryServiceDef->setArguments([new Reference($channelFactoryServiceId), new Reference($exchangeDefinitionServiceId)]);

                $container->setDefinition($exchangeFactoryServiceId, $exchangeFactoryServiceDef);
            } else {
                // Use default channel
                $exchangeFactoryServiceId = $this->exchangeFactories[$publisher['exchange']];
            }

            // Configure middleware for consumer
            $publisherMiddlewares = \array_merge($globalMiddlewares, $publisher['middleware']);

            $middlewareList = \array_map(static function (string $serviceId) {
                return new Reference($serviceId);
            }, $publisherMiddlewares);

            $middlewareServiceId = \sprintf('fivelab.amqp.publisher.%s.middlewares', $key);
            $middlewareServiceDef = new ChildDefinition('fivelab.amqp.publisher.middlewares.abstract');
            $middlewareServiceDef->setArguments($middlewareList);

            $container->setDefinition($middlewareServiceId, $middlewareServiceDef);

            // Configure publisher
            $publisherServiceId = \sprintf('fivelab.amqp.publisher.%s', $key);

            if ($publisher['savepoint']) {
                // Create original publisher
                $originalPublisherServiceDef = new ChildDefinition('fivelab.amqp.publisher.abstract');

                $originalPublisherServiceDef
                    ->replaceArgument(0, new Reference($exchangeFactoryServiceId))
                    ->replaceArgument(1, new Reference($middlewareServiceId));

                $container->setDefinition($publisherServiceId.'.origin', $originalPublisherServiceDef);

                // Create decorator
                $publisherServiceDef = new ChildDefinition('fivelab.amqp.publisher.savepoint.abstract');

                $publisherServiceDef
                    ->replaceArgument(0, new Reference($publisherServiceId.'.origin'));

                $this->savepointPublishers[$key] = $publisherServiceId;
            } else {
                $publisherServiceDef = new ChildDefinition('fivelab.amqp.publisher.abstract');

                $publisherServiceDef
                    ->replaceArgument(0, new Reference($exchangeFactoryServiceId))
                    ->replaceArgument(1, new Reference($middlewareServiceId));
            }

            $container->setDefinition($publisherServiceId, $publisherServiceDef);

            $publishersRegistryDef->addMethodCall('add', [
                $key,
                new Reference($publisherServiceId),
            ]);

            $this->publishers[$key] = $publisherServiceId;
        }

        $container->setParameter('fivelab.amqp.publishers', \array_keys($this->publishers));
        $container->setParameter('fivelab.amqp.savepoint_publishers', \array_keys($this->savepointPublishers));
    }

    private function configureRoundRobin(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition('fivelab.amqp.round_robin_consumer.configuration')
            ->replaceArgument(0, $config['executes_messages_per_consumer'])
            ->replaceArgument(1, $config['consumers_read_timeout'])
            ->replaceArgument(2, 0);

        $container->getDefinition('fivelab.amqp.round_robin_consumer')
            ->replaceArgument(2, \array_keys($this->consumers));
    }

    private function configureDelay(ContainerBuilder $container, array $config, array $globalPublisherMiddlewares, array $globalConsumerMiddlewares, array $consumerEventHandlers, array $queueDefaultArguments, array $consumerDefaults): void
    {
        // Configure exchange
        $this->configureExchanges($container, [
            $config['exchange'] => [
                'name'       => $config['exchange'],
                'connection' => $config['connection'],
                'type'       => 'direct',
                'durable'    => true,
                'passive'    => false,
                'bindings'   => [],
                'unbindings' => [],
            ],
        ]);

        // Configure expired queue
        $this->configureQueues($container, [
            $config['expired_queue'] => [
                'name'        => $config['expired_queue'],
                'connection'  => $config['connection'],
                'durable'     => true,
                'passive'     => false,
                'exclusive'   => false,
                'auto_delete' => false,
                'bindings'    => [
                    [
                        'exchange' => $config['exchange'],
                        'routing'  => 'message.expired',
                    ],
                ],
                'unbindings'  => [],
            ],
        ], $queueDefaultArguments);

        $messageHandlerServiceIds = [];

        foreach ($config['delays'] as $key => $delayInfo) {
            $this->configureQueues($container, [
                $delayInfo['queue'] => [
                    'connection'  => $config['connection'],
                    'name'        => $delayInfo['queue'],
                    'durable'     => true,
                    'passive'     => false,
                    'exclusive'   => false,
                    'auto_delete' => false,
                    'bindings'    => [
                        ['exchange' => $config['exchange'], 'routing' => $delayInfo['routing']],
                    ],
                    'unbindings'  => [],
                    'arguments'   => [
                        'dead-letter-exchange'    => $config['exchange'],
                        'dead-letter-routing-key' => 'message.expired',
                        'message-ttl'             => $delayInfo['ttl'],
                    ],
                ],
            ], $queueDefaultArguments);

            // Add default publisher
            $delayInfo['publishers'][$key] = [
                'channel'   => null,
                'savepoint' => false,
            ];

            $publisherServiceId = null;

            // Configure publishers
            foreach ($delayInfo['publishers'] as $publisherKey => $publisherInfo) {
                $publisherKey = $publisherKey === $key ? $publisherKey : $key.'_'.$publisherKey;

                $this->configurePublishers($container, [
                    $publisherKey => [
                        'exchange'   => $config['exchange'],
                        'savepoint'  => $publisherInfo['savepoint'],
                        'channel'    => $publisherInfo['channel'],
                        'middleware' => [],
                    ],
                ], $globalPublisherMiddlewares);

                $publisherServiceId = $this->publishers[$publisherKey];

                $delayPublisherDef = new ChildDefinition('fivelab.amqp.delay.publisher.abstract');
                $delayPublisherDef
                    ->replaceArgument(0, new Reference($publisherServiceId.'.delay.inner'))
                    ->replaceArgument(1, $delayInfo['routing'])
                    ->setDecoratedService($publisherServiceId);

                $container->setDefinition($publisherServiceId.'.delay', $delayPublisherDef);
            }

            // Configure message handler
            $messageHandlerServiceId = \sprintf('fivelab.amqp.delay.message_handler.%s', $key);
            $messageHandlerServiceIds[] = $messageHandlerServiceId;
            $messageHandlerServiceDef = new ChildDefinition('fivelab.amqp.delay.message_handler.abstract');

            $messageHandlerServiceDef
                ->replaceArgument(1, new Reference($publisherServiceId.'.delay.inner'))
                ->replaceArgument(2, $delayInfo['routing']);

            $container->setDefinition($messageHandlerServiceId, $messageHandlerServiceDef);
        }

        // Configure consumer for handle expired messages
        $this->configureConsumers($container, [
            $config['consumer_key'] => [
                'queue'            => $config['expired_queue'],
                'mode'             => 'loop',
                'strategy'         => $config['strategy'],
                'channel'          => '',
                'message_handlers' => $messageHandlerServiceIds,
                'checker'          => '',
                'middleware'       => [],
                'tag_generator'    => '',
                'options'          => [
                    'read_timeout'     => 300,
                    'requeue_on_error' => true,
                    'prefetch_count'   => 3,
                    'idle_timeout'     => 100000,
                ],
            ],
        ], $globalConsumerMiddlewares, $consumerEventHandlers, $consumerDefaults);
    }

    private function createArgumentDefinition(ContainerBuilder $container, string $serviceId, string $class, mixed ...$values): Reference
    {
        $definition = new Definition($class);
        $definition->setArguments($values);

        $container->setDefinition($serviceId, $definition);

        return new Reference($serviceId);
    }

    private function createCustomArgumentDefinition(ContainerBuilder $container, string $serviceId, string $name, mixed $value): Reference
    {
        $definition = new ChildDefinition('fivelab.amqp.definition.argument.abstract');

        $definition->setArguments([$name, $value]);

        $container->setDefinition($serviceId, $definition);

        return new Reference($serviceId);
    }

    private function createCustomArgumentDefinitions(ContainerBuilder $container, string $serviceIdPrefix, array $arguments): array
    {
        $references = [];

        foreach ($arguments as $name => $value) {
            $serviceId = \sprintf('%s.%s', $serviceIdPrefix, \str_replace('-', '_', $name));

            $references[] = $this->createCustomArgumentDefinition($container, $serviceId, $name, $value);
        }

        return $references;
    }

    private static function resolveBoolOrExpression(mixed $value): Expression|bool
    {
        if (\is_string($value) && \str_starts_with($value, '@=')) {
            return new Expression(\substr($value, 2));
        }

        return (bool) $value;
    }
}
