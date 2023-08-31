<?php

use FiveLab\Bundle\AmqpBundle\Connection\Registry\ConnectionFactoryRegistry;
use FiveLab\Bundle\AmqpBundle\Connection\Registry\ConnectionFactoryRegistryInterface;
use FiveLab\Component\Amqp\Command\InitializeExchangesCommand;
use FiveLab\Component\Amqp\Command\InitializeQueuesCommand;
use FiveLab\Component\Amqp\Command\ListConsumersCommand;
use FiveLab\Component\Amqp\Command\RunConsumerCommand;
use FiveLab\Component\Amqp\Consumer\Registry\ConsumerRegistryInterface;
use FiveLab\Component\Amqp\Consumer\Registry\ContainerConsumerRegistry;
use FiveLab\Component\Amqp\Exchange\Registry\ExchangeFactoryRegistry;
use FiveLab\Component\Amqp\Exchange\Registry\ExchangeFactoryRegistryInterface;
use FiveLab\Component\Amqp\Publisher\Registry\PublisherRegistry;
use FiveLab\Component\Amqp\Publisher\Registry\PublisherRegistryInterface;
use FiveLab\Component\Amqp\Queue\Registry\QueueFactoryRegistry;
use FiveLab\Component\Amqp\Queue\Registry\QueueFactoryRegistryInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    // Console commands.
    $container->services()
        ->set('fivelab.amqp.console_command.run_consumer', RunConsumerCommand::class)
            ->args([
                service('fivelab.amqp.consumer_registry')
            ])
            ->tag('console.command')

        ->set('fivelab.amqp.console_command.initialize_exchanges', InitializeExchangesCommand::class)
            ->args([
                service('fivelab.amqp.exchange_factory_registry'),
                abstract_arg('list of exchanges')
            ])
            ->tag('console.command')

        ->set('fivelab.amqp.console_command.initialize_queues', InitializeQueuesCommand::class)
            ->args([
                service('fivelab.amqp.queue_factory_registry'),
                abstract_arg('list of queues')
            ])
            ->tag('console.command')

        ->set('fivelab.amqp.console_command.list_consumers', ListConsumersCommand::class)
            ->args([
                abstract_arg('list of consumers')
            ])
            ->tag('console.command');

    // Registries
    $container->services()
        ->set('fivelab.amqp.consumer_registry', ContainerConsumerRegistry::class)
            ->public()
            ->args([
                abstract_arg('service locator')
            ])

        ->set('fivelab.amqp.exchange_factory_registry', ExchangeFactoryRegistry::class)
            ->public()

        ->set('fivelab.amqp.queue_factory_registry', QueueFactoryRegistry::class)
            ->public()

        ->set('fivelab.amqp.connection_factory_registry', ConnectionFactoryRegistry::class)
            ->public()

        ->set('fivelab.amqp.publisher_registry', PublisherRegistry::class)
            ->public();

    // Aliases
    $container->services()
        ->alias(ConsumerRegistryInterface::class, 'fivelab.amqp.consumer_registry')
        ->alias(ExchangeFactoryRegistryInterface::class, 'fivelab.amqp.queue_factory_registry')
        ->alias(QueueFactoryRegistryInterface::class, 'fivelab.amqp.queue_factory_registry')
        ->alias(ConnectionFactoryRegistryInterface::class, 'fivelab.amqp.connection_factory_registry')
        ->alias(PublisherRegistryInterface::class, 'fivelab.amqp.publisher_registry');
};
