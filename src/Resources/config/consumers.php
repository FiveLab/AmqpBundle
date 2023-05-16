<?php

use FiveLab\Component\Amqp\Consumer\ConsumerConfiguration;
use FiveLab\Component\Amqp\Consumer\Handler\MessageHandlers;
use FiveLab\Component\Amqp\Consumer\Loop\LoopConsumer;
use FiveLab\Component\Amqp\Consumer\Loop\LoopConsumerConfiguration;
use FiveLab\Component\Amqp\Consumer\Middleware\ConsumerMiddlewares;
use FiveLab\Component\Amqp\Consumer\SingleConsumer;
use FiveLab\Component\Amqp\Consumer\Spool\SpoolConsumer;
use FiveLab\Component\Amqp\Consumer\Spool\SpoolConsumerConfiguration;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;

return static function (ContainerConfigurator $container) {
    $container->services()
        // Single consumer
        ->set('fivelab.amqp.consumer_single.configuration.abstract', ConsumerConfiguration::class)
            ->abstract()
            ->args([
                abstract_arg('requeue on error?'),
                abstract_arg('count messages'),
                abstract_arg('tag name generator')
            ])

        ->set('fivelab.amqp.consumer_single.abstract', SingleConsumer::class)
            ->abstract()
            ->args([
                abstract_arg('queue factory'),
                abstract_arg('message handler'),
                abstract_arg('middleware'),
                abstract_arg('configuration')
            ])

        // Spool consumer
        ->set('fivelab.amqp.consumer_spool.configuration.abstract', SpoolConsumerConfiguration::class)
            ->abstract()
            ->args([
                abstract_arg('count messages'),
                abstract_arg('timeout'),
                abstract_arg('read timeout'),
                abstract_arg('requeue on error?'),
                abstract_arg('tag name generator')
            ])

        ->set('fivelab.amqp.consumer_spool.abstract', SpoolConsumer::class)
            ->abstract()
            ->args([
                abstract_arg('queue factory'),
                abstract_arg('message handler'),
                abstract_arg('middleware'),
                abstract_arg('configuration')
            ])

        // Loop consumer
        ->set('fivelab.amqp.consumer_loop.configuration.abstract', LoopConsumerConfiguration::class)
            ->abstract()
            ->args([
                abstract_arg('read timeout'),
                abstract_arg('requeue on error?'),
                abstract_arg('count messages'),
                abstract_arg('tag name generator')
            ])

        ->set('fivelab.amqp.consumer_loop.abstract', LoopConsumer::class)
            ->abstract()
            ->args([
                abstract_arg('queue factory'),
                abstract_arg('message handler'),
                abstract_arg('middleware'),
                abstract_arg('configuration')
            ])

        // Common services
        ->set('fivelab.amqp.consumer.message_handler.abstract', MessageHandlers::class)
            ->abstract()

        ->set('fivelab.amqp.consumer.middlewares.abstract', ConsumerMiddlewares::class)
            ->abstract();
};
