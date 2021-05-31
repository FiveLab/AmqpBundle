<?php

declare(strict_types = 1);

namespace FiveLab\Bundle\AmqpBundle\Tests\DependencyInjection;

use FiveLab\Bundle\AmqpBundle\DependencyInjection\AmqpExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Reference;

class AmqpExtensionTest extends AbstractExtensionTestCase
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
    public function shouldSuccessProcessWithoutConfiguration(): void
    {
        $this->load([]);

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     *
     * @param string $driver
     *
     * @dataProvider provideDrivers
     */
    public function shouldAllServiceClassExist(string $driver): void
    {
        $this->load([
            'driver' => $driver,
        ]);

        foreach ($this->container->getDefinitions() as $serviceId => $definition) {
            $class = $definition->getClass();
            $class = $this->container->getParameterBag()->resolveValue($class);

            self::assertTrue(
                \class_exists($class) || \interface_exists($class),
                \sprintf(
                    'The class "%s" for service "%s" was not found.',
                    $class,
                    $serviceId
                )
            );
        }
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureRoundRobin(): void
    {
        $this->load([
            'connections' => [
                'default' => [
                    'host' => 'host',
                    'port' => 5672,
                ],
            ],

            'round_robin' => [
                'enable'                         => true,
                'executes_messages_per_consumer' => 50,
                'consumers_read_timeout'         => 5.0,
            ],

            'queues' => [
                'foo' => [
                    'connection' => 'default',
                ],

                'bar' => [
                    'connection' => 'default',
                ],
            ],

            'consumers' => [
                'foo' => [
                    'queue'            => 'foo',
                    'message_handlers' => ['handler'],
                ],

                'bar' => [
                    'queue'            => 'bar',
                    'message_handlers' => ['handler'],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithTag('fivelab.amqp.console_command.run_round_robin_consumer', 'console.command');
        $this->assertContainerBuilderHasService('fivelab.amqp.round_robin_consumer');
        $this->assertContainerBuilderHasService('fivelab.amqp.round_robin_consumer.configuration');

        $command = $this->container->getDefinition('fivelab.amqp.console_command.run_round_robin_consumer');
        self::assertEquals(new Reference('fivelab.amqp.round_robin_consumer'), $command->getArgument(0));

        $configuration = $this->container->getDefinition('fivelab.amqp.round_robin_consumer.configuration');
        self::assertEquals(50, $configuration->getArgument(0));
        self::assertEquals(5.0, $configuration->getArgument(1));
        self::assertEquals(0, $configuration->getArgument(2));

        $consumer = $this->container->getDefinition('fivelab.amqp.round_robin_consumer');

        self::assertEquals([
            new Reference('fivelab.amqp.round_robin_consumer.configuration'),
            new Reference('fivelab.amqp.consumer.foo'),
            new Reference('fivelab.amqp.consumer.bar'),
        ], $consumer->getArguments());
    }

    /**
     * @test
     *
     * @param string $registry
     *
     * @dataProvider providePublicRegistries
     */
    public function shouldRegistriesIsPublic(string $registry): void
    {
        $this->load([]);

        $definition = $this->container->getDefinition($registry);

        self::assertTrue($definition->isPublic(), \sprintf(
            'The service "%s" must be public.',
            $registry
        ));
    }

    /**
     * Provide data for testing public registries
     *
     * @return array
     */
    public function providePublicRegistries(): array
    {
        return [
            ['fivelab.amqp.consumer_registry'],
            ['fivelab.amqp.exchange_factory_registry'],
            ['fivelab.amqp.queue_factory_registry'],
            ['fivelab.amqp.connection_factory_registry'],
            ['fivelab.amqp.publisher_registry'],
        ];
    }

    /**
     * Provide drivers
     *
     * @return \string[][]
     */
    public function provideDrivers(): array
    {
        return [
            'lib'         => ['php_lib'],
            'lib_sockets' => ['php_lib_sockets'],
            'ext'         => ['php_extension'],
        ];
    }
}
