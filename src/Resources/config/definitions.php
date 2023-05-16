<?php

use FiveLab\Component\Amqp\Argument\ArgumentDefinition;
use FiveLab\Component\Amqp\Argument\ArgumentDefinitions;
use FiveLab\Component\Amqp\Binding\Definition\BindingDefinition;
use FiveLab\Component\Amqp\Binding\Definition\BindingDefinitions;
use FiveLab\Component\Amqp\Channel\Definition\ChannelDefinition;
use FiveLab\Component\Amqp\Exchange\Definition\ExchangeDefinition;
use FiveLab\Component\Amqp\Queue\Definition\QueueDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('fivelab.amqp.definition.argument.abstract', ArgumentDefinition::class)
            ->abstract()
            ->args([
                abstract_arg('argument name'),
                abstract_arg('argument value')
            ])

        ->set('fivelab.amqp.definition.arguments.abstract', ArgumentDefinitions::class)
            ->abstract()

        ->set('fivelab.amqp.definition.channel.abstract', ChannelDefinition::class)
            ->abstract()

        ->set('fivelab.amqp.definition.exchange.abstract', ExchangeDefinition::class)
            ->abstract()
            ->args([
                abstract_arg('name'),
                abstract_arg('type'),
                abstract_arg('durable'),
                abstract_arg('passive'),
                abstract_arg('arguments'),
                abstract_arg('bindings'),
                abstract_arg('unbindings')
            ])

        ->set('fivelab.amqp.definition.queue.abstract', QueueDefinition::class)
            ->abstract()
            ->args([
                abstract_arg('name'),
                abstract_arg('bindings'),
                abstract_arg('unbindings'),
                abstract_arg('durable'),
                abstract_arg('passive'),
                abstract_arg('exclusive'),
                abstract_arg('auto delete'),
                abstract_arg('arguments')
            ])

        ->set('fivelab.amqp.definition.binding.abstract', BindingDefinition::class)
            ->abstract()
            ->args([
                abstract_arg('exchange name'),
                abstract_arg('routing key')
            ])

        ->set('fivelab.amqp.definition.bindings', BindingDefinitions::class)
            ->abstract();
};
