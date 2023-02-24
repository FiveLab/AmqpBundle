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

namespace FiveLab\Bundle\AmqpBundle\Tests;

use FiveLab\Bundle\AmqpBundle\Connection\Registry\ConnectionFactoryRegistry;
use FiveLab\Bundle\AmqpBundle\DependencyInjection\AmqpExtension;
use FiveLab\Bundle\AmqpBundle\FiveLabAmqpBundle;
use FiveLab\Component\Amqp\Connection\ConnectionFactoryInterface;
use FiveLab\Component\Amqp\Connection\ConnectionInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FiveLabAmqpBundleTest extends TestCase
{
    /**
     * @test
     */
    public function shouldSuccessGetExtension(): void
    {
        $bundle = new FiveLabAmqpBundle();

        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(AmqpExtension::class, $extension);
    }

    /**
     * @test
     */
    public function shouldSuccessShutdown(): void
    {
        list ($factory1, $connection1) = $this->createConnectionWithFactory();
        list ($factory2, $connection2) = $this->createConnectionWithFactory();
        list ($factory3, $connection3) = $this->createConnectionWithFactory();

        $connection1->expects(self::once())
            ->method('isConnected')
            ->willReturn(true);

        $connection1->expects(self::once())
            ->method('disconnect');

        $connection2->expects(self::once())
            ->method('isConnected')
            ->willReturn(false);

        $connection2->expects(self::never())
            ->method('disconnect');

        $connection3->expects(self::once())
            ->method('isConnected')
            ->willReturn(true);

        $connection3->expects(self::once())
            ->method('disconnect');

        $registry = new ConnectionFactoryRegistry();
        $registry->add('connection1', $factory1);
        $registry->add('connection2', $factory2);
        $registry->add('connection3', $factory3);

        $container = $this->createMock(ContainerInterface::class);

        $container->expects(self::once())
            ->method('get')
            ->with('fivelab.amqp.connection_factory_registry')
            ->willReturn($registry);

        $container->expects(self::once())
            ->method('getParameter')
            ->with('fivelab.amqp.connection_factories')
            ->willReturn(['connection1', 'connection2', 'connection3']);

        $bundle = new FiveLabAmqpBundle();
        $bundle->setContainer($container);

        $bundle->shutdown();
    }

    /**
     * Create connection with factory
     *
     * @return array
     */
    private function createConnectionWithFactory(): array
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $factory = $this->createMock(ConnectionFactoryInterface::class);

        $factory->expects(self::any())
            ->method('create')
            ->willReturn($connection);

        return [$factory, $connection];
    }
}
