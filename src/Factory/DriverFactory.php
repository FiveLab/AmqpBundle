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

namespace FiveLab\Bundle\AmqpBundle\Factory;

use FiveLab\Component\Amqp\Adapter\Amqp\Channel\AmqpChannelFactory as AmqpExtChannelFactory;
use FiveLab\Component\Amqp\Adapter\Amqp\Exchange\AmqpExchangeFactory as AmqpExtExchangeFactory;
use FiveLab\Component\Amqp\Adapter\Amqp\Queue\AmqpQueueFactory as AmqpExtQueueFactory;
use FiveLab\Component\Amqp\Adapter\AmqpLib\Channel\AmqpChannelFactory as AmqpLibChannelFactory;
use FiveLab\Component\Amqp\Adapter\AmqpLib\Exchange\AmqpExchangeFactory as AmqpLibExchangeFactory;
use FiveLab\Component\Amqp\Adapter\AmqpLib\Queue\AmqpQueueFactory as AmqpLibQueueFactory;
use FiveLab\Component\Amqp\Channel\ChannelFactoryInterface;
use FiveLab\Component\Amqp\Channel\Definition\ChannelDefinition;
use FiveLab\Component\Amqp\Connection\ConnectionFactoryInterface;
use FiveLab\Component\Amqp\Connection\Driver;
use FiveLab\Component\Amqp\Connection\Dsn;
use FiveLab\Component\Amqp\Connection\SpoolConnectionFactory;
use FiveLab\Component\Amqp\Exchange\Definition\ExchangeDefinition;
use FiveLab\Component\Amqp\Exchange\ExchangeFactoryInterface;
use FiveLab\Component\Amqp\Queue\Definition\QueueDefinition;
use FiveLab\Component\Amqp\Queue\QueueFactoryInterface;

/**
 * A factory for create required services based on driver.
 */
class DriverFactory
{
    /**
     * Constructor.
     *
     * @param Dsn $dsn
     */
    public function __construct(private Dsn $dsn)
    {
    }

    /**
     * Create connection factory based on DSN.
     *
     * @return ConnectionFactoryInterface
     */
    public function createConnectionFactory(): ConnectionFactoryInterface
    {
        return SpoolConnectionFactory::fromDsn($this->dsn);
    }

    /**
     * Create channel factory based on DSN.
     *
     * @param ConnectionFactoryInterface $connectionFactory
     * @param ChannelDefinition          $definition
     *
     * @return ChannelFactoryInterface
     */
    public function createChannelFactory(ConnectionFactoryInterface $connectionFactory, ChannelDefinition $definition): ChannelFactoryInterface
    {
        $factoryClass = match ($this->dsn->driver) {
            Driver::AmqpExt                      => AmqpExtChannelFactory::class,
            Driver::AmqpLib, Driver::AmqpSockets => AmqpLibChannelFactory::class,
        };

        return new $factoryClass($connectionFactory, $definition);
    }

    /**
     * Create exchange factory based on DSN.
     *
     * @param ChannelFactoryInterface $channelFactory
     * @param ExchangeDefinition      $definition
     *
     * @return ExchangeFactoryInterface
     */
    public function createExchangeFactory(ChannelFactoryInterface $channelFactory, ExchangeDefinition $definition): ExchangeFactoryInterface
    {
        $factoryClass = match ($this->dsn->driver) {
            Driver::AmqpExt                      => AmqpExtExchangeFactory::class,
            Driver::AmqpLib, Driver::AmqpSockets => AmqpLibExchangeFactory::class,
        };

        return new $factoryClass($channelFactory, $definition);
    }

    /**
     * Create queue factory based on DSN.
     *
     * @param ChannelFactoryInterface $channelFactory
     * @param QueueDefinition         $definition
     *
     * @return QueueFactoryInterface
     */
    public function createQueueFactory(ChannelFactoryInterface $channelFactory, QueueDefinition $definition): QueueFactoryInterface
    {
        $factoryClass = match ($this->dsn->driver) {
            Driver::AmqpExt                      => AmqpExtQueueFactory::class,
            Driver::AmqpLib, Driver::AmqpSockets => AmqpLibQueueFactory::class,
        };

        return new $factoryClass($channelFactory, $definition);
    }
}
