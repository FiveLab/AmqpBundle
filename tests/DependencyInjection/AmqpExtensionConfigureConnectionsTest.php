<?php

declare(strict_types = 1);

namespace FiveLab\Bundle\AmqpBundle\Tests\DependencyInjection;

use FiveLab\Bundle\AmqpBundle\DependencyInjection\AmqpExtension;
use FiveLab\Component\Amqp\Connection\SpoolConnection;
use FiveLab\Component\Amqp\Connection\SpoolConnectionFactory;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Reference;

class AmqpExtensionConfigureConnectionsTest extends AbstractExtensionTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getContainerExtensions(): array
    {
        return [new AmqpExtension()];
    }

    /**
     * {@inheritdoc}
     */
    protected function getMinimalConfiguration(): array
    {
        return [
            'driver' => 'php_extension',
        ];
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureWithLibAdapter(): void
    {
        $this->load([
            'connections' => [
                'connection' => [
                    'host'     => 'host1',
                    'port'     => 5672,
                    'vhost'    => '/',
                    'login'    => 'foo',
                    'password' => 'bar',
                ],
            ],
        ]);

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureConnections(): void
    {
        $this->load([
            'connections' => [
                'connection1' => [
                    'host'     => 'host1',
                    'port'     => 5672,
                    'vhost'    => '/',
                    'login'    => 'foo',
                    'password' => 'bar',
                ],

                'connection2' => [
                    'host'         => 'host2',
                    'port'         => 5673,
                    'vhost'        => '/bar',
                    'login'        => 'user',
                    'password'     => 'pass',
                    'read_timeout' => 60,
                    'heartbeat'    => 30,
                ],
            ],
        ]);

        // Verify first connection
        $this->assertContainerBuilderHasService('fivelab.amqp.connection_factory.connection1');
        $connection1 = $this->container->getDefinition('fivelab.amqp.connection_factory.connection1');

        self::assertEquals(new Reference('fivelab.amqp.connection_factory.connection1_0'), $connection1->getArgument(0));
        $this->assertContainerBuilderHasService('fivelab.amqp.connection_factory.connection1_0');

        $connection10 = $this->container->getDefinition('fivelab.amqp.connection_factory.connection1_0');

        self::assertEquals([
            'host'         => 'host1',
            'port'         => 5672,
            'vhost'        => '/',
            'login'        => 'foo',
            'password'     => 'bar',
            'read_timeout' => 0,
            'heartbeat'    => 0,
        ], $connection10->getArgument(0));

        // Verify second connection
        $this->assertContainerBuilderHasService('fivelab.amqp.connection_factory.connection2');
        $connection2 = $this->container->getDefinition('fivelab.amqp.connection_factory.connection2');

        self::assertEquals(new Reference('fivelab.amqp.connection_factory.connection2_0'), $connection2->getArgument(0));
        $this->assertContainerBuilderHasService('fivelab.amqp.connection_factory.connection2_0');

        $connection20 = $this->container->getDefinition('fivelab.amqp.connection_factory.connection2_0');

        self::assertEquals([
            'host'         => 'host2',
            'port'         => 5673,
            'vhost'        => '/bar',
            'login'        => 'user',
            'password'     => 'pass',
            'read_timeout' => 60,
            'heartbeat'    => 30,
        ], $connection20->getArgument(0));

        $this->assertContainerBuilderHasParameter('fivelab.amqp.connection_factories', [
            'connection1',
            'connection2',
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            'fivelab.amqp.connection_factory_registry',
            'add',
            ['connection1', new Reference('fivelab.amqp.connection_factory.connection1')],
            0
        );

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            'fivelab.amqp.connection_factory_registry',
            'add',
            ['connection2', new Reference('fivelab.amqp.connection_factory.connection2')],
            1
        );
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureConnectionsWithMultipleHosts(): void
    {
        $this->load([
            'connections' => [
                'default' => [
                    'host'      => ['host1', 'host2'],
                    'port'      => 5672,
                    'vhost'     => '/',
                    'login'     => 'foo',
                    'password'  => 'bar',
                    'heartbeat' => 60,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.connection_factory.default');
        $originConnection = $this->container->getDefinition('fivelab.amqp.connection_factory.default');

        self::assertEquals(new Reference('fivelab.amqp.connection_factory.default_0'), $originConnection->getArgument(0));
        self::assertEquals(new Reference('fivelab.amqp.connection_factory.default_1'), $originConnection->getArgument(1));

        $this->assertContainerBuilderHasService('fivelab.amqp.connection_factory.default_0');
        $this->assertContainerBuilderHasService('fivelab.amqp.connection_factory.default_1');

        $connection1 = $this->container->getDefinition('fivelab.amqp.connection_factory.default_0');

        self::assertEquals([
            'host'         => 'host1',
            'port'         => 5672,
            'vhost'        => '/',
            'login'        => 'foo',
            'password'     => 'bar',
            'read_timeout' => 0,
            'heartbeat'    => 60,
        ], $connection1->getArgument(0));

        $connection2 = $this->container->getDefinition('fivelab.amqp.connection_factory.default_1');

        self::assertEquals([
            'host'         => 'host2',
            'port'         => 5672,
            'vhost'        => '/',
            'login'        => 'foo',
            'password'     => 'bar',
            'read_timeout' => 0,
            'heartbeat'    => 60,
        ], $connection2->getArgument(0));
    }
}
