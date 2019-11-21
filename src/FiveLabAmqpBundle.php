<?php

/*
 * This file is part of the FiveLab AmqpBundle package
 *
 * (c) FiveLab
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

declare(strict_types = 1);

namespace FiveLab\Bundle\AmqpBundle;

use FiveLab\Bundle\AmqpBundle\Connection\Registry\ConnectionFactoryRegistryInterface;
use FiveLab\Bundle\AmqpBundle\DependencyInjection\AmqpExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * The bundle for integrate the AMQP library with you Symfony application.
 */
class FiveLabAmqpBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): AmqpExtension
    {
        if (!$this->extension) {
            $this->extension = new AmqpExtension();
        }

        return $this->extension;
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown(): void
    {
        /** @var ConnectionFactoryRegistryInterface $connectionFactoryRegistry */
        $connectionFactoryRegistry = $this->container->get('fivelab.amqp.connection_factory_registry');
        $connectionFactories = $this->container->getParameter('fivelab.amqp.connection_factories');

        foreach ($connectionFactories as $connectionFactoryKey) {
            $connectionFactory = $connectionFactoryRegistry->get($connectionFactoryKey);
            $connection = $connectionFactory->create();

            if ($connection->isConnected()) {
                $connection->disconnect();
            }
        }
    }
}
