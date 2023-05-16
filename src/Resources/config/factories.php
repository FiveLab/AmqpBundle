<?php

use FiveLab\Component\Amqp\Connection\SpoolConnectionFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('fivelab.amqp.spool_connection_factory.abstract', SpoolConnectionFactory::class)
            ->abstract()

        ->set('fivelab.amqp.connection_factory.abstract', param('fivelab.amqp.connection_factory.class'))
            ->abstract()
            ->args([
                abstract_arg('connection parameters')
            ])

        ->set('fivelab.amqp.channel_factory.abstract', param('fivelab.amqp.channel_factory.class'))
            ->abstract()
            ->args([
                abstract_arg('channel factory'),
                abstract_arg('channel definition')
            ])

        ->set('fivelab.amqp.exchange_factory.abstract', param('fivelab.amqp.exchange_factory.class'))
            ->abstract()
            ->args([
                abstract_arg('channel factory'),
                abstract_arg('exchange definition')
            ])

        ->set('fivelab.amqp.queue_factory.abstract', param('fivelab.amqp.queue_factory.class'))
            ->abstract()
            ->args([
                abstract_arg('channel factory'),
                abstract_arg('queue definition')
            ]);
};
