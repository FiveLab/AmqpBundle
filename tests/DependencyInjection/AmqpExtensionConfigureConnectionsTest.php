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

namespace FiveLab\Bundle\AmqpBundle\Tests\DependencyInjection;

use FiveLab\Bundle\AmqpBundle\Connection\Registry\ConnectionFactoryRegistry;
use FiveLab\Bundle\AmqpBundle\Factory\DriverFactory;
use FiveLab\Component\Amqp\Channel\ChannelFactoryInterface;
use FiveLab\Component\Amqp\Connection\ConnectionFactoryInterface;
use FiveLab\Component\Amqp\Connection\Dsn;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DependencyInjection\Reference;

class AmqpExtensionConfigureConnectionsTest extends AmqpExtensionTestCase
{
    #[Test]
    public function shouldSuccessConfigureSingleConnection(): void
    {
        $this->load([
            'connections' => [
                'dsn' => 'amqp://localhost',
            ],
        ]);

        // Check driver
        $this->assertService(
            'fivelab.amqp.connection_dsn.default',
            Dsn::class,
            ['amqp://localhost'],
            [Dsn::class, 'fromDsn']
        );

        $this->assertService('fivelab.amqp.driver_factory.default', DriverFactory::class, [new Reference('fivelab.amqp.connection_dsn.default')]);

        // Check connection
        $this->assertService(
            'fivelab.amqp.connection_factory.default',
            ConnectionFactoryInterface::class,
            [],
            [new Reference('fivelab.amqp.driver_factory.default'), 'createConnectionFactory']
        );

        // Check default channel
        $this->assertService('fivelab.amqp.channel_definition.default', '@fivelab.amqp.definition.channel.abstract', []);

        $this->assertService(
            'fivelab.amqp.channel_factory.default',
            ChannelFactoryInterface::class,
            [new Reference('fivelab.amqp.connection_factory.default'), new Reference('fivelab.amqp.channel_definition.default')],
        );
    }

    #[Test]
    public function shouldSuccessConfigureMultipleConnections(): void
    {
        $this->load([
            'connections' => [
                'connection1' => [
                    'dsn' => 'amqp://localhost:5672',
                ],

                'connection2' => [
                    'dsn' => 'amqp://localhost:5673',
                ],
            ],
        ]);

        // Verify first connection
        $this->assertService(
            'fivelab.amqp.connection_dsn.connection1',
            Dsn::class,
            ['amqp://localhost:5672'],
            [Dsn::class, 'fromDsn']
        );

        $this->assertService('fivelab.amqp.driver_factory.connection1', DriverFactory::class, [new Reference('fivelab.amqp.connection_dsn.connection1')]);

        $this->assertService(
            'fivelab.amqp.connection_factory.connection1',
            ConnectionFactoryInterface::class,
            [],
            [new Reference('fivelab.amqp.driver_factory.connection1'), 'createConnectionFactory']
        );

        $this->assertService('fivelab.amqp.channel_definition.connection1', '@fivelab.amqp.definition.channel.abstract', []);

        $this->assertService(
            'fivelab.amqp.channel_factory.connection1',
            ChannelFactoryInterface::class,
            [new Reference('fivelab.amqp.connection_factory.connection1'), new Reference('fivelab.amqp.channel_definition.connection1')],
        );

        // Verify second connection
        $this->assertService(
            'fivelab.amqp.connection_dsn.connection2',
            Dsn::class,
            ['amqp://localhost:5673'],
            [Dsn::class, 'fromDsn']
        );

        $this->assertService('fivelab.amqp.driver_factory.connection2', DriverFactory::class, [new Reference('fivelab.amqp.connection_dsn.connection2')]);

        $this->assertService(
            'fivelab.amqp.connection_factory.connection2',
            ConnectionFactoryInterface::class,
            [],
            [new Reference('fivelab.amqp.driver_factory.connection2'), 'createConnectionFactory']
        );

        $this->assertService('fivelab.amqp.channel_definition.connection2', '@fivelab.amqp.definition.channel.abstract', []);

        $this->assertService(
            'fivelab.amqp.channel_factory.connection2',
            ChannelFactoryInterface::class,
            [new Reference('fivelab.amqp.connection_factory.connection2'), new Reference('fivelab.amqp.channel_definition.connection2')],
        );

        // Additional checks
        $this->assertParameter('fivelab.amqp.connection_factories', [
            'connection1',
            'connection2',
        ]);

        $this->assertService('fivelab.amqp.connection_factory_registry', ConnectionFactoryRegistry::class, calls: [
            'add' => [
                ['connection1', new Reference('fivelab.amqp.connection_factory.connection1')],
                ['connection2', new Reference('fivelab.amqp.connection_factory.connection2')],
            ],
        ]);
    }
}
