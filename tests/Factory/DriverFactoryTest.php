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

namespace FiveLab\Bundle\AmqpBundle\Tests\Factory;

use FiveLab\Bundle\AmqpBundle\Factory\DriverFactory;
use FiveLab\Component\Amqp\Adapter\Amqp\Channel\AmqpChannelFactory as AmqpExtChannelFactory;
use FiveLab\Component\Amqp\Adapter\Amqp\Exchange\AmqpExchangeFactory as AmqpExtExchangeFactory;
use FiveLab\Component\Amqp\Adapter\Amqp\Queue\AmqpQueueFactory as AmqpExtQueueFactory;
use FiveLab\Component\Amqp\Adapter\AmqpLib\Channel\AmqpChannelFactory as AmqpLibChannelFactory;
use FiveLab\Component\Amqp\Adapter\AmqpLib\Exchange\AmqpExchangeFactory as AmqpLibExchangeFactory;
use FiveLab\Component\Amqp\Adapter\AmqpLib\Queue\AmqpQueueFactory as AmqpLibQueueFactory;
use FiveLab\Component\Amqp\Channel\Definition\ChannelDefinition;
use FiveLab\Component\Amqp\Connection\Driver;
use FiveLab\Component\Amqp\Connection\Dsn;
use FiveLab\Component\Amqp\Connection\SpoolConnectionFactory;
use FiveLab\Component\Amqp\Exchange\Definition\ExchangeDefinition;
use FiveLab\Component\Amqp\Queue\Definition\QueueDefinition;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class DriverFactoryTest extends TestCase
{
    #[Test]
    #[TestWith([Driver::AmqpExt, SpoolConnectionFactory::class])]
    #[TestWith([Driver::AmqpLib, SpoolConnectionFactory::class])]
    #[TestWith([Driver::AmqpSockets, SpoolConnectionFactory::class])]
    public function shouldSuccessCreateConnectionFactory(Driver $driver, string $expectedClass): void
    {
        $dsn = Dsn::fromDsn($driver->value.'://localhost');

        $factory = (new DriverFactory($dsn))->createConnectionFactory();

        self::assertInstanceOf($expectedClass, $factory);
    }

    #[Test]
    #[Depends('shouldSuccessCreateConnectionFactory')]
    #[TestWith([Driver::AmqpExt, AmqpExtChannelFactory::class])]
    #[TestWith([Driver::AmqpLib, AmqpLibChannelFactory::class])]
    #[TestWith([Driver::AmqpSockets, AmqpLibChannelFactory::class])]
    public function shouldSuccessCreateChannelFactory(Driver $driver, string $expectedClass): void
    {
        $dsn = Dsn::fromDsn($driver->value.'://localhost');

        $driverFactory = new DriverFactory($dsn);
        $connectionFactory = $driverFactory->createConnectionFactory();

        $factory = (new DriverFactory($dsn))->createChannelFactory($connectionFactory, new ChannelDefinition());

        self::assertInstanceOf($expectedClass, $factory);
    }

    #[Test]
    #[Depends('shouldSuccessCreateChannelFactory')]
    #[TestWith([Driver::AmqpExt, AmqpExtExchangeFactory::class])]
    #[TestWith([Driver::AmqpLib, AmqpLibExchangeFactory::class])]
    #[TestWith([Driver::AmqpSockets, AmqpLibExchangeFactory::class])]
    public function shouldSuccessCreateExchangeFactory(Driver $driver, string $expectedClass): void
    {
        $dsn = Dsn::fromDsn($driver->value.'://localhost');

        $driverFactory = new DriverFactory($dsn);
        $connectionFactory = $driverFactory->createConnectionFactory();
        $channelFactory = $driverFactory->createChannelFactory($connectionFactory, new ChannelDefinition());

        $factory = $driverFactory->createExchangeFactory($channelFactory, new ExchangeDefinition('foo', 'direct'));

        self::assertInstanceOf($expectedClass, $factory);
    }

    #[Test]
    #[Depends('shouldSuccessCreateChannelFactory')]
    #[TestWith([Driver::AmqpExt, AmqpExtQueueFactory::class])]
    #[TestWith([Driver::AmqpLib, AmqpLibQueueFactory::class])]
    #[TestWith([Driver::AmqpSockets, AmqpLibQueueFactory::class])]
    public function shouldSuccessCreateQueueFactory(Driver $driver, string $expectedClass): void
    {
        $dsn = Dsn::fromDsn($driver->value.'://localhost');

        $driverFactory = new DriverFactory($dsn);
        $connectionFactory = $driverFactory->createConnectionFactory();
        $channelFactory = $driverFactory->createChannelFactory($connectionFactory, new ChannelDefinition());

        $factory = $driverFactory->createQueueFactory($channelFactory, new QueueDefinition('foo'));

        self::assertInstanceOf($expectedClass, $factory);
    }
}
