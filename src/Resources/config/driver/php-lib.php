<?php

use FiveLab\Component\Amqp\Adapter\AmqpLib\Channel\AmqpChannelFactory;
use FiveLab\Component\Amqp\Adapter\AmqpLib\Connection\AmqpConnectionFactory;
use FiveLab\Component\Amqp\Adapter\AmqpLib\Exchange\AmqpExchangeFactory;
use FiveLab\Component\Amqp\Adapter\AmqpLib\Queue\AmqpQueueFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('fivelab.amqp.connection_factory.class', AmqpConnectionFactory::class)
        ->set('fivelab.amqp.channel_factory.class', AmqpChannelFactory::class)
        ->set('fivelab.amqp.exchange_factory.class', AmqpExchangeFactory::class)
        ->set('fivelab.amqp.queue_factory.class', AmqpQueueFactory::class);
};
