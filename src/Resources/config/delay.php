<?php

use FiveLab\Component\Amqp\Consumer\Handler\HandleExpiredMessageHandler;
use FiveLab\Component\Amqp\Publisher\DelayPublisher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('fivelab.amqp.delay.message_handler.abstract', HandleExpiredMessageHandler::class)
            ->abstract()
            ->args([
                service('fivelab.amqp.publisher_registry'),
                abstract_arg('delay publisher'),
                abstract_arg('landfill routing key')
            ])

        ->set('fivelab.amqp.delay.publisher.abstract', DelayPublisher::class)
            ->abstract()
            ->args([
                abstract_arg('decorated publisher'),
                abstract_arg('landfill routing key')
            ]);
};
