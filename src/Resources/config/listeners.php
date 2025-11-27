<?php

use FiveLab\Bundle\AmqpBundle\Listener\PingDbalConnectionsListener;
use FiveLab\Bundle\AmqpBundle\Listener\ReleaseMemoryListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(ReleaseMemoryListener::class, ReleaseMemoryListener::class)
            ->abstract()
            ->args([
                service('services_resetter'),
                abstract_arg('release before handle')
            ])
            ->tag('kernel.event_subscriber')

        ->set(PingDbalConnectionsListener::class, PingDbalConnectionsListener::class)
            ->abstract()
            ->args([
                service('doctrine'),
                abstract_arg('ping interval')
            ])
            ->tag('kernel.event_subscriber')
    ;
};
