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
use Symfony\Component\ExpressionLanguage\Expression;
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
    private array $connectionFactories = [];

    /**
     * The list of available default channels (for each connection).
     *
     * @var array
     */
    private array $defaultChannelFactories = [];

    /**
     * The list of specific channel factories.
     *
     * @var array
     */
    private array $channelFactories = [];

    /**
     * The list key pair with channel name and connection name.
     *
     * @var array
     */
    private array $channelConnections;

    /**
     * The list of available exchange factories
     *
     * @var array
     */
    private array $exchangeFactories = [];

    /**
     * List list key pair with exchange name and connection name
     *
     * @var array
     */
    private array $exchangeConnections = [];

    /**
     * The list of available queue factories
     *
     * @var array
     */
    private array $queueFactories = [];

    /**
     * The list key pair with queue name and connection name
     *
     * @var array
     */
    private array $queueConnections = [];

    /**
     * The list of available consumers
     *
     * @var array
     */
    private array $consumers = [];

    /**
     * The list of available publishers
     *
     * @var array
     */
    private array $publishers = [];

    /**
     * The list of available savepoint publishers
     *
     * @var array
     */
    private array $savepointPublishers = [];

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('factories.xml');
        $loader->load('definitions.xml');
        $loader->load('consumers.xml');
        $loader->load('publishers.xml');
        $loader->load('services.xml');

        if ('php_extension' === $config['driver']) {
            $loader->load('driver/php-extension.xml');
        }

        if ('php_lib' === $config['driver']) {
            $loader->load('driver/php-lib.xml');
        }

        $this->configureConnections($container, $config['connections']);
        $this->configureChannels($container, $config['channels']);
        $this->configureExchanges($container, $config['exchanges']);
        $this->configureQueues($container, $config['queues']);
        $this->configurePublishers($container, $config['publishers'], $config['publisher_middleware']);
        $this->configureConsumers($container, $config['consumers'], $config['consumer_middleware']);

        if ($config['round_robin']['enable']) {
            $loader->load('round-robin.xml');

            $this->configureRoundRobin($container, $config['round_robin']);
        }

        if (\array_key_exists('delay', $config) && $config['delay']) {
            $loader->load('delay.xml');

            $this->configureDelay($container, $config['delay'], $config['publisher_middleware'], $config['consumer_middleware']);
        }

        $container->getDefinition('fivelab.amqp.console_command.initialize_exchanges')
            ->replaceArgument(1, \array_keys($this->exchangeFactories));

        $container->getDefinition('fivelab.amqp.console_command.initialize_queues')
            ->replaceArgument(1, \array_keys($this->queueFactories));

        $container->getDefinition('fivelab.amqp.console_command.list_consumers')
            ->replaceArgument(0, \array_keys($this->consumers));
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'fivelab_amqp';
    }

    /**
     * Configure connections. For each connection we create a default channel use in exchanges and queues.
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
                    'heartbeat'    => $connection['heartbeat'],
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

            // Create default channel definition service definition
            $channelDefinitionServiceId = \sprintf('fivelab.amqp.channel_definition.%s', $key);
            $channelDefinitionServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.channel.abstract');

            $container->setDefinition($channelDefinitionServiceId, $channelDefinitionServiceDefinition);

            // Create default channel factory service definition
            $channelFactoryServiceId = \sprintf('fivelab.amqp.channel_factory.%s', $key);
            $channelFactoryServiceDefinition = $this->createChildDefinition('fivelab.amqp.channel_factory.abstract');

            $channelFactoryServiceDefinition
                ->replaceArgument(0, new Reference($originConnectionFactoryServiceId))
                ->replaceArgument(1, new Reference($channelDefinitionServiceId));

            $container->setDefinition($channelFactoryServiceId, $channelFactoryServiceDefinition);

            $this->defaultChannelFactories[$key] = $channelFactoryServiceId;
        }

        $container->setParameter('fivelab.amqp.connection_factories', \array_keys($this->connectionFactories));
    }

    /**
     * Configure channels
     *
     * @param ContainerBuilder $container
     * @param array            $channels
     */
    private function configureChannels(ContainerBuilder $container, array $channels): void
    {
        foreach ($channels as $key => $channel) {
            $connectionKey = $channel['connection'];

            if (!\array_key_exists($connectionKey, $this->connectionFactories)) {
                throw new \RuntimeException(\sprintf(
                    'Can\'t configure channel "%s". Connection "%s" was not found.',
                    $key,
                    $connectionKey
                ));
            }

            $connectionFactoryServiceId = $this->connectionFactories[$connectionKey];

            // Create channel definition
            $channelDefinitionServiceId = \sprintf('fivelab.amqp.channel_definition.%s.%s', $connectionKey, $key);
            $channelDefinitionServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.channel.abstract');

            $container->setDefinition($channelDefinitionServiceId, $channelDefinitionServiceDefinition);

            $channelFactoryServiceId = \sprintf('fivelab.amqp.channel_factory.%s.%s', $connectionKey, $key);
            $channelFactoryServiceDefinition = $this->createChildDefinition('fivelab.amqp.channel_factory.abstract');

            $channelFactoryServiceDefinition
                ->replaceArgument(0, new Reference($connectionFactoryServiceId))
                ->replaceArgument(1, new Reference($channelDefinitionServiceId));

            $container->setDefinition($channelFactoryServiceId, $channelFactoryServiceDefinition);

            $this->channelFactories[$key] = $channelFactoryServiceId;
            $this->channelConnections[$key] = $connectionKey;
        }
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

            // Configure bindings
            $bindingReferences = [];
            $bindingsServiceId = \sprintf('fivelab.amqp.exchange_definition.%s.bindings', $key);
            $bindingsServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.bindings');

            foreach ($exchange['bindings'] as $binding) {
                $bindingServiceId = \sprintf(
                    'fivelab.amqp.exchange_definition.%s.binding.%s_%s',
                    $key,
                    $binding['exchange'],
                    $binding['routing']
                );

                $bindingServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.binding.abstract');
                $bindingServiceDefinition
                    ->replaceArgument(0, $binding['exchange'])
                    ->replaceArgument(1, $binding['routing']);

                $container->setDefinition($bindingServiceId, $bindingServiceDefinition);

                $bindingReferences[] = new Reference($bindingServiceId);
            }

            $bindingsServiceDefinition->setArguments($bindingReferences);
            $container->setDefinition($bindingsServiceId, $bindingsServiceDefinition);

            $unbingingReferences = [];
            $unbindingsServiceId = \sprintf('fivelab.amqp.exchange_definition.%s.unbindings', $key);
            $unbindingsServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.bindings');

            foreach ($exchange['unbindings'] as $unbinding) {
                $unbindingServiceId = \sprintf(
                    'fivelab.amqp.exchange_definition.%s.unbinding.%s_%s',
                    $key,
                    $unbinding['exchange'],
                    $unbinding['routing']
                );

                $unbindingServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.binding.abstract');
                $unbindingServiceDefinition
                    ->replaceArgument(0, $unbinding['exchange'])
                    ->replaceArgument(1, $unbinding['routing']);

                $container->setDefinition($unbindingServiceId, $unbindingServiceDefinition);

                $unbingingReferences[] = new Reference($unbindingServiceId);
            }

            $unbindingsServiceDefinition->setArguments($unbingingReferences);
            $container->setDefinition($unbindingsServiceId, $unbindingsServiceDefinition);

            // Create exchange arguments
            $argumentsServiceId = null;

            if (\array_key_exists('arguments', $exchange)) {
                $exchangeArguments = $exchange['arguments'];

                $argumentsServiceId = \sprintf('fivelab.amqp.exchange_definition.%s.arguments', $key);
                $argumentsServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.arguments.abstract');
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

                $argumentsServiceDefinition->setArguments($argumentReferences);

                $container->setDefinition($argumentsServiceId, $argumentsServiceDefinition);
            }

            // Create exchange definition service definition
            $exchangeDefinitionServiceId = \sprintf('fivelab.amqp.exchange_definition.%s', $key);
            $exchangeDefinitionServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.exchange.abstract');

            $exchangeDefinitionServiceDefinition
                ->replaceArgument(0, 'amq.default' === $exchange['name'] ? '' : $exchange['name'])
                ->replaceArgument(1, $exchange['type'])
                ->replaceArgument(2, (bool) $exchange['durable'])
                ->replaceArgument(3, self::resolveBoolOrExpression($exchange['passive']))
                ->replaceArgument(4, $argumentsServiceId ? new Reference($argumentsServiceId) : null)
                ->replaceArgument(5, new Reference($bindingsServiceId))
                ->replaceArgument(6, new Reference($unbindingsServiceId));

            $container->setDefinition($exchangeDefinitionServiceId, $exchangeDefinitionServiceDefinition);

            // Create exchange factory service definition
            $exchangeFactoryServiceId = \sprintf('fivelab.amqp.exchange_factory.%s', $key);
            $exchangeFactoryServiceDefinition = $this->createChildDefinition('fivelab.amqp.exchange_factory.abstract');

            $exchangeFactoryServiceDefinition
                ->replaceArgument(0, new Reference($this->defaultChannelFactories[$exchange['connection']]))
                ->replaceArgument(1, new Reference($exchangeDefinitionServiceId));

            $container->setDefinition($exchangeFactoryServiceId, $exchangeFactoryServiceDefinition);

            $this->exchangeFactories[$key] = $exchangeFactoryServiceId;
            $this->exchangeConnections[$key] = $exchange['connection'];

            $registryDefinition->addMethodCall('add', [
                $key,
                new Reference($exchangeFactoryServiceId),
            ]);
        }

        $container->setParameter('fivelab.amqp.exchange_factories', \array_keys($this->exchangeFactories));
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
            $bindingsServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.bindings');

            foreach ($queue['bindings'] as $binding) {
                $bindingServiceId = \sprintf(
                    'fivelab.amqp.queue_definition.%s.binding.%s_%s',
                    $key,
                    $binding['exchange'],
                    $binding['routing']
                );

                $bindingServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.binding.abstract');
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
            $unbindingsServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.bindings');

            foreach ($queue['unbindings'] as $unbinding) {
                $unbindingServiceId = \sprintf(
                    'fivelab.amqp.queue_definition.%s.unbinding.%s_%s',
                    $key,
                    $unbinding['exchange'],
                    $unbinding['routing']
                );

                $unbindingServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.binding.abstract');
                $unbindingServiceDefinition
                    ->replaceArgument(0, $unbinding['exchange'])
                    ->replaceArgument(1, $unbinding['routing']);

                $container->setDefinition($unbindingServiceId, $unbindingServiceDefinition);

                $unbingingReferences[] = new Reference($unbindingServiceId);
            }

            $unbindingsServiceDefinition->setArguments($unbingingReferences);
            $container->setDefinition($unbindingsServiceId, $unbindingsServiceDefinition);

            // Create arguments
            $argumentsServiceId = null;

            if (\array_key_exists('arguments', $queue)) {
                $queueArguments = $queue['arguments'];

                $argumentsServiceId = \sprintf('fivelab.amqp.queue_definition.%s.arguments', $key);
                $argumentsServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.arguments.abstract');
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

                $argumentsServiceDefinition->setArguments($argumentReferences);

                $container->setDefinition($argumentsServiceId, $argumentsServiceDefinition);
            }

            // Configure queue definition service definition
            $queueDefinitionServiceId = \sprintf('fivelab.amqp.queue_definition.%s', $key);
            $queueDefinitionServiceDefinition = $this->createChildDefinition('fivelab.amqp.definition.queue.abstract');

            $queueDefinitionServiceDefinition
                ->replaceArgument(0, $queue['name'])
                ->replaceArgument(1, new Reference($bindingsServiceId))
                ->replaceArgument(2, new Reference($unbindingsServiceId))
                ->replaceArgument(3, (bool) $queue['durable'])
                ->replaceArgument(4, self::resolveBoolOrExpression($queue['passive']))
                ->replaceArgument(5, (bool) $queue['exclusive'])
                ->replaceArgument(6, (bool) $queue['auto_delete'])
                ->replaceArgument(7, $argumentsServiceId ? new Reference($argumentsServiceId) : null);

            $container->setDefinition($queueDefinitionServiceId, $queueDefinitionServiceDefinition);

            // Configure queue factory service definition
            $queueFactoryServiceId = \sprintf('fivelab.amqp.queue_factory.%s', $key);
            $queueFactoryServiceDefinition = $this->createChildDefinition('fivelab.amqp.queue_factory.abstract');

            $queueFactoryServiceDefinition
                ->replaceArgument(0, new Reference($this->defaultChannelFactories[$queue['connection']]))
                ->replaceArgument(1, new Reference($queueDefinitionServiceId));

            $container->setDefinition($queueFactoryServiceId, $queueFactoryServiceDefinition);

            $this->queueFactories[$key] = $queueFactoryServiceId;
            $this->queueConnections[$key] = $queue['connection'];

            $queueRegistry->addMethodCall('add', [
                $key,
                new Reference($queueFactoryServiceId),
            ]);
        }

        $container->setParameter('fivelab.amqp.queue_factories', \array_keys($this->queueFactories));
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
                $queueFactoryServiceDefinition = $this->createChildDefinition('fivelab.amqp.queue_factory.abstract');

                $queueFactoryServiceDefinition
                    ->replaceArgument(0, new Reference($channelFactoryServiceId))
                    ->replaceArgument(1, new Reference($queueDefinitionServiceId));

                $container->setDefinition($queueFactoryServiceId, $queueFactoryServiceDefinition);
            } else {
                $queueFactoryServiceId = $this->queueFactories[$consumer['queue']];
            }

            // Configure middleware for consumer
            $consumerMiddlewares = \array_merge($globalMiddlewares, $consumer['middleware']);

            $middlewareList = \array_map(static function (string $serviceId) {
                return new Reference($serviceId);
            }, $consumerMiddlewares);

            $middlewareServiceId = \sprintf('fivelab.amqp.consumer.%s.middlewares', $key);
            $middlewareServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer.middlewares.abstract');
            $middlewareServiceDefinition->setArguments($middlewareList);

            $container->setDefinition($middlewareServiceId, $middlewareServiceDefinition);

            // Configure message handler for consumer
            $messageHandlersServiceId = \sprintf('fivelab.amqp.consumer.%s.message_handler', $key);
            $messageHandlersServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer.message_handler.abstract');

            $index = 0;

            foreach ($consumer['message_handlers'] as $messageHandler) {
                $messageHandlersServiceDefinition->setArgument($index++, new Reference($messageHandler));
            }

            $container->setDefinition($messageHandlersServiceId, $messageHandlersServiceDefinition);

            // Configure consumer
            $consumerServiceId = \sprintf('fivelab.amqp.consumer.%s', $key);
            $tagNameGenerator = null;

            if ($consumer['tag_generator']) {
                $tagNameGenerator = new Reference($consumer['tag_generator']);
            }

            if ('single' === $consumer['mode']) {
                // Configure single consumer
                $consumerConfigurationServiceId = \sprintf('fivelab.amqp.consumer.%s.configuration', $key);
                $consumerConfigurationServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer_single.configuration.abstract');

                $consumerConfigurationServiceDefinition
                    ->replaceArgument(0, $consumer['options']['requeue_on_error'])
                    ->replaceArgument(1, $consumer['options']['prefetch_count'])
                    ->replaceArgument(2, $tagNameGenerator);

                $consumerServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer_single.abstract');

                $consumerServiceDefinition
                    ->replaceArgument(0, new Reference($queueFactoryServiceId))
                    ->replaceArgument(1, new Reference($messageHandlersServiceId))
                    ->replaceArgument(2, new Reference($middlewareServiceId))
                    ->replaceArgument(3, new Reference($consumerConfigurationServiceId));
            } else if ('spool' === $consumer['mode']) {
                // Configure spool consumer
                $consumerConfigurationServiceId = \sprintf('fivelab.amqp.consumer.%s.configuration', $key);
                $consumerConfigurationServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer_spool.configuration.abstract');

                $consumerConfigurationServiceDefinition
                    ->replaceArgument(0, $consumer['options']['prefetch_count'])
                    ->replaceArgument(1, $consumer['options']['timeout'])
                    ->replaceArgument(2, $consumer['options']['read_timeout'])
                    ->replaceArgument(3, $consumer['options']['requeue_on_error'])
                    ->replaceArgument(4, $tagNameGenerator);

                $consumerServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer_spool.abstract');

                $consumerServiceDefinition
                    ->replaceArgument(0, new Reference($queueFactoryServiceId))
                    ->replaceArgument(1, new Reference($messageHandlersServiceId))
                    ->replaceArgument(2, new Reference($middlewareServiceId))
                    ->replaceArgument(3, new Reference($consumerConfigurationServiceId));
            } else if ('loop' === $consumer['mode']) {
                $consumerConfigurationServiceId = \sprintf('fivelab.amqp.consumer.%s.configuration', $key);
                $consumerConfigurationServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer_loop.configuration.abstract');

                $consumerConfigurationServiceDefinition
                    ->replaceArgument(0, $consumer['options']['read_timeout'])
                    ->replaceArgument(1, $consumer['options']['requeue_on_error'])
                    ->replaceArgument(2, $consumer['options']['prefetch_count'])
                    ->replaceArgument(3, $tagNameGenerator);

                $consumerServiceDefinition = $this->createChildDefinition('fivelab.amqp.consumer_loop.abstract');

                $consumerServiceDefinition
                    ->replaceArgument(0, new Reference($queueFactoryServiceId))
                    ->replaceArgument(1, new Reference($messageHandlersServiceId))
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

        $container->setParameter('fivelab.amqp.consumers', \array_keys($this->consumers));
    }

    /**
     * Configure publishers
     *
     * @param ContainerBuilder $container
     * @param array            $publishers
     * @param array            $globalMiddlewares
     */
    private function configurePublishers(ContainerBuilder $container, array $publishers, array $globalMiddlewares): void
    {
        $publishersRegistryDefinition = $container->getDefinition('fivelab.amqp.publisher_registry');

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

                // Create new exchange factory with created exchange definition.
                $channelFactoryServiceId = $this->channelFactories[$publisher['channel']];
                $exchangeDefinitionServiceId = \sprintf('fivelab.amqp.exchange_definition.%s', $publisher['exchange']);

                $exchangeFactoryServiceId = \sprintf('fivelab.amqp.exchange_factory.%s.%s', $publisher['exchange'], $key);
                $exchangeFactoryServiceDefinition = $this->createChildDefinition('fivelab.amqp.exchange_factory.abstract');

                $exchangeFactoryServiceDefinition
                    ->replaceArgument(0, new Reference($channelFactoryServiceId))
                    ->replaceArgument(1, new Reference($exchangeDefinitionServiceId));

                $container->setDefinition($exchangeFactoryServiceId, $exchangeFactoryServiceDefinition);
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
            $middlewareServiceDefinition = $this->createChildDefinition('fivelab.amqp.publisher.middlewares.abstract');
            $middlewareServiceDefinition->setArguments($middlewareList);

            $container->setDefinition($middlewareServiceId, $middlewareServiceDefinition);

            // Configure publisher
            $publisherServiceId = \sprintf('fivelab.amqp.publisher.%s', $key);

            if ($publisher['savepoint']) {
                // Create original publisher
                $originalPublisherServiceDefinition = $this->createChildDefinition('fivelab.amqp.publisher.abstract');

                $originalPublisherServiceDefinition
                    ->replaceArgument(0, new Reference($exchangeFactoryServiceId))
                    ->replaceArgument(1, new Reference($middlewareServiceId));

                $container->setDefinition($publisherServiceId.'.origin', $originalPublisherServiceDefinition);

                // Create decorator
                $publisherServiceDefinition = $this->createChildDefinition('fivelab.amqp.publisher.savepoint.abstract');

                $publisherServiceDefinition
                    ->replaceArgument(0, new Reference($publisherServiceId.'.origin'));

                $this->savepointPublishers[$key] = $publisherServiceId;
            } else {
                $publisherServiceDefinition = $this->createChildDefinition('fivelab.amqp.publisher.abstract');

                $publisherServiceDefinition
                    ->replaceArgument(0, new Reference($exchangeFactoryServiceId))
                    ->replaceArgument(1, new Reference($middlewareServiceId));
            }

            $container->setDefinition($publisherServiceId, $publisherServiceDefinition);

            $publishersRegistryDefinition->addMethodCall('add', [
                $key,
                new Reference($publisherServiceId),
            ]);

            $this->publishers[$key] = $publisherServiceId;
        }

        $container->setParameter('fivelab.amqp.publishers', \array_keys($this->publishers));
        $container->setParameter('fivelab.amqp.savepoint_publishers', \array_keys($this->savepointPublishers));
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
     * Configure delay system
     *
     * @param ContainerBuilder $container
     * @param array            $config
     * @param array            $globalPublisherMiddlewares
     * @param array            $globalConsumerMiddlewares
     */
    private function configureDelay(ContainerBuilder $container, array $config, array $globalPublisherMiddlewares, array $globalConsumerMiddlewares): void
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
        ]);

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
                        'queue-type'              => 'classic', // Force use classic because quorum not support TTL for messages.
                        'dead-letter-exchange'    => $config['exchange'],
                        'dead-letter-routing-key' => 'message.expired',
                        'message-ttl'             => $delayInfo['ttl'],
                    ],
                ],
            ]);

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

                $delayPublisherDefinition = $this->createChildDefinition('fivelab.amqp.delay.publisher.abstract');
                $delayPublisherDefinition
                    ->replaceArgument(0, new Reference($publisherServiceId.'.delay.inner'))
                    ->replaceArgument(1, $delayInfo['routing'])
                    ->setDecoratedService($publisherServiceId);

                $container->setDefinition($publisherServiceId.'.delay', $delayPublisherDefinition);
            }

            // Configure message handler
            $messageHandlerServiceId = \sprintf('fivelab.amqp.delay.message_handler.%s', $key);
            $messageHandlerServiceIds[] = $messageHandlerServiceId;
            $messageHandlerServiceDefinition = $this->createChildDefinition('fivelab.amqp.delay.message_handler.abstract');

            $messageHandlerServiceDefinition
                ->replaceArgument(1, new Reference($publisherServiceId.'.delay.inner'))
                ->replaceArgument(2, $delayInfo['routing']);

            $container->setDefinition($messageHandlerServiceId, $messageHandlerServiceDefinition);
        }

        // Configure consumer for handle expired messages
        $this->configureConsumers($container, [
            $config['consumer_key'] => [
                'queue'            => $config['expired_queue'],
                'mode'             => 'loop',
                'channel'          => '',
                'message_handlers' => $messageHandlerServiceIds,
                'middleware'       => [],
                'tag_generator'    => '',
                'options'          => [
                    'read_timeout'     => 300,
                    'requeue_on_error' => true,
                    'prefetch_count'   => 3,
                ],
            ],
        ], $globalConsumerMiddlewares);
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

    /**
     * Resolve bool or expression object
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private static function resolveBoolOrExpression($value)
    {
        if (\is_string($value) && 0 === \strpos($value, '@=')) {
            return new Expression(\substr($value, 2));
        }

        return (bool) $value;
    }
}
