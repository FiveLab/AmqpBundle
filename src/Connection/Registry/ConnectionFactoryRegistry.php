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

namespace FiveLab\Bundle\AmqpBundle\Connection\Registry;

use FiveLab\Bundle\AmqpBundle\Exception\ConnectionFactoryNotFoundException;
use FiveLab\Component\Amqp\Connection\ConnectionFactoryInterface;

/**
 * Default connection factory registry.
 */
class ConnectionFactoryRegistry implements ConnectionFactoryRegistryInterface
{
    /**
     * @var array|ConnectionFactoryInterface
     */
    private $connectionFactories = [];

    /**
     * Add connection factory to registry
     *
     * @param string                     $name
     * @param ConnectionFactoryInterface $connectionFactory
     */
    public function add(string $name, ConnectionFactoryInterface $connectionFactory): void
    {
        $this->connectionFactories[$name] = $connectionFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): ConnectionFactoryInterface
    {
        if (\array_key_exists($name, $this->connectionFactories)) {
            return $this->connectionFactories[$name];
        }

        throw new ConnectionFactoryNotFoundException(\sprintf(
            'The connection factory "%s" was not found.',
            $name
        ));
    }
}
