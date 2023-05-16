<?php

use FiveLab\Component\Amqp\Publisher\Middleware\PublisherMiddlewares;
use FiveLab\Component\Amqp\Publisher\Publisher;
use FiveLab\Component\Amqp\Publisher\SavepointPublisherDecorator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('fivelab.amqp.publisher.abstract', Publisher::class)
            ->abstract()
            ->args([
                abstract_arg('exchange factory'),
                abstract_arg('middlewares')
            ])

        ->set('fivelab.amqp.publisher.savepoint.abstract', SavepointPublisherDecorator::class)
            ->abstract()
            ->args([
                abstract_arg('decorated publisher')
            ])

        ->set('fivelab.amqp.publisher.middlewares.abstract', PublisherMiddlewares::class)
            ->abstract();
};
