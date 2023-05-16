<?php

use FiveLab\Component\Amqp\Command\RunRoundRobinConsumerCommand;
use FiveLab\Component\Amqp\Consumer\RoundRobin\RoundRobinConsumer;
use FiveLab\Component\Amqp\Consumer\RoundRobin\RoundRobinConsumerConfiguration;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('fivelab.amqp.console_command.run_round_robin_consumer', RunRoundRobinConsumerCommand::class)
            ->args([
                service('fivelab.amqp.round_robin_consumer')
            ])
            ->tag('console.command')

        ->set('fivelab.amqp.round_robin_consumer.configuration', RoundRobinConsumerConfiguration::class)
            ->args([
                abstract_arg('messages per one consumer'),
                abstract_arg('read timeout for consumer'),
                abstract_arg('full timeout')
            ])

        ->set('fivelab.amqp.round_robin_consumer', RoundRobinConsumer::class)
            ->args([
                service('fivelab.amqp.round_robin_consumer.configuration'),
                service('fivelab.amqp.consumer_registry'),
                abstract_arg('list of consumers')
            ]);
};