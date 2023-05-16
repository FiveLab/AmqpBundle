<?php

use FiveLab\Bundle\AmqpBundle\Connection\Registry\ConnectionFactoryRegistry;
use FiveLab\Component\Amqp\Command\InitializeExchangesCommand;
use FiveLab\Component\Amqp\Command\InitializeQueuesCommand;
use FiveLab\Component\Amqp\Command\ListConsumersCommand;
use FiveLab\Component\Amqp\Command\RunConsumerCommand;
use FiveLab\Component\Amqp\Consumer\Registry\ContainerConsumerRegistry;
use FiveLab\Component\Amqp\Exchange\Registry\ExchangeFactoryRegistry;
use FiveLab\Component\Amqp\Publisher\Registry\PublisherRegistry;
use FiveLab\Component\Amqp\Queue\Registry\QueueFactoryRegistry;
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
};
