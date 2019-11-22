<?php

declare(strict_types = 1);

namespace FiveLab\Bundle\AmqpBundle\Tests\DependencyInjection;

use FiveLab\Bundle\AmqpBundle\DependencyInjection\AmqpExtension;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\DeadLetterExchangeArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\DeadLetterRoutingKeyArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\ExpiresArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\MaxLengthArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\MaxLengthBytesArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\MaxPriorityArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\MessageTtlArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\OverflowArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\QueueMasterLocatorArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\QueueModeArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\QueueTypeArgument;
use FiveLab\Component\Amqp\Queue\Definition\Arguments\SingleActiveCustomerArgument;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Reference;

class AmqpExtensionConfigureQueuesTest extends AbstractExtensionTestCase
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
            'driver'      => 'php_extension',
            'connections' => [
                'default' => [
                    'host' => 'host1',
                ],

                'custom' => [
                    'host' => 'custom',
                ],
            ],
        ];
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureWithMinimalConfiguration(): void
    {
        $this->load([
            'queues' => [
                'default' => [
                    'connection' => 'default',
                ],
            ],
        ]);

        // Check factory
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_factory.default',
            0,
            new Reference('fivelab.amqp.channel_factory.default')
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_factory.default',
            1,
            new Reference('fivelab.amqp.queue_definition.default')
        );

        // Check queue definition
        $queueDefinition = $this->container->getDefinition('fivelab.amqp.queue_definition.default');

        self::assertEquals([
            'default',
            'fivelab.amqp.queue_definition.default.bindings',
            'fivelab.amqp.queue_definition.default.unbindings',
            true,
            false,
            false,
            false,
            new Reference('fivelab.amqp.queue_definition.default.arguments'),
        ], \array_values($queueDefinition->getArguments()));
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureWithCustomConfiguration(): void
    {
        $this->load([
            'queues' => [
                'default' => [
                    'connection'  => 'default',
                    'name'        => 'my-test',
                    'durable'     => false,
                    'passive'     => true,
                    'exclusive'   => true,
                    'auto_delete' => true,
                ],
            ],
        ]);

        $queueDefinition = $this->container->getDefinition('fivelab.amqp.queue_definition.default');

        self::assertEquals([
            'my-test',
            'fivelab.amqp.queue_definition.default.bindings',
            'fivelab.amqp.queue_definition.default.unbindings',
            false,
            true,
            true,
            true,
            new Reference('fivelab.amqp.queue_definition.default.arguments'),
        ], \array_values($queueDefinition->getArguments()));
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureWithBindings(): void
    {
        $this->load([
            'queues' => [
                'default' => [
                    'connection' => 'default',
                    'bindings'   => [
                        ['exchange' => 'test', 'routing' => 'foo'],
                        ['exchange' => 'test', 'routing' => 'bar'],
                    ],
                ],
            ],
        ]);

        $queueDefinition = $this->container->getDefinition('fivelab.amqp.queue_definition.default');

        self::assertEquals(new Reference('fivelab.amqp.queue_definition.default.bindings'), $queueDefinition->getArgument(1));

        $this->assertContainerBuilderHasService('fivelab.amqp.queue_definition.default.bindings');

        self::assertEquals([
            new Reference('fivelab.amqp.queue_definition.default.binding.test_foo'),
            new Reference('fivelab.amqp.queue_definition.default.binding.test_bar'),
        ], \array_values($this->container->getDefinition('fivelab.amqp.queue_definition.default.bindings')->getArguments()));

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_definition.default.binding.test_foo',
            0,
            'test'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_definition.default.binding.test_foo',
            1,
            'foo'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_definition.default.binding.test_bar',
            0,
            'test'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_definition.default.binding.test_bar',
            1,
            'bar'
        );
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureWithUnbindings(): void
    {
        $this->load([
            'queues' => [
                'default' => [
                    'connection' => 'default',
                    'unbindings' => [
                        ['exchange' => 'test', 'routing' => 'foo'],
                        ['exchange' => 'test', 'routing' => 'bar'],
                    ],
                ],
            ],
        ]);

        $queueDefinition = $this->container->getDefinition('fivelab.amqp.queue_definition.default');

        self::assertEquals(new Reference('fivelab.amqp.queue_definition.default.unbindings'), $queueDefinition->getArgument(2));

        $this->assertContainerBuilderHasService('fivelab.amqp.queue_definition.default.unbindings');

        self::assertEquals([
            new Reference('fivelab.amqp.queue_definition.default.unbinding.test_foo'),
            new Reference('fivelab.amqp.queue_definition.default.unbinding.test_bar'),
        ], \array_values($this->container->getDefinition('fivelab.amqp.queue_definition.default.unbindings')->getArguments()));

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_definition.default.unbinding.test_foo',
            0,
            'test'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_definition.default.unbinding.test_foo',
            1,
            'foo'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_definition.default.unbinding.test_bar',
            0,
            'test'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_definition.default.unbinding.test_bar',
            1,
            'bar'
        );
    }

    /**
     * @test
     *
     * @param string $argumentName
     * @param mixed  $argumentValue
     * @param string $expectedAggumentClass
     * @param bool   $checkValue
     *
     * @dataProvider provideArguments
     */
    public function shouldSuccessConfigureWithArguments(string $argumentName, $argumentValue, string $expectedAggumentClass, bool $checkValue = true): void
    {
        $this->load([
            'queues' => [
                'default' => [
                    'connection' => 'default',
                    'arguments'  => [
                        $argumentName => $argumentValue,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.queue_definition.default.arguments');
        $argumentsDefinition = $this->container->getDefinition('fivelab.amqp.queue_definition.default.arguments');

        self::assertCount(1, $argumentsDefinition->getArguments());

        /** @var Reference $argumentDefinition */
        $argumentDefinition = $argumentsDefinition->getArgument(0);

        self::assertInstanceOf(Reference::class, $argumentDefinition);

        $argumentOriginalDefinition = $this->container->getDefinition((string) $argumentDefinition);

        self::assertEquals($expectedAggumentClass, $argumentOriginalDefinition->getClass());

        if ($checkValue) {
            self::assertEquals($argumentValue, $argumentOriginalDefinition->getArgument(0));
        }
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureWithCustomArguments(): void
    {
        $this->load([
            'queues' => [
                'default' => [
                    'connection' => 'default',
                    'arguments'  => [
                        'custom' => [
                            'x-some'     => 'foo',
                            'x-some-too' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.queue_definition.default.arguments');
        $argumentsDefinition = $this->container->getDefinition('fivelab.amqp.queue_definition.default.arguments');

        self::assertEquals([
            new Reference('fivelab.amqp.queue_definition.default.arguments.x_some'),
            new Reference('fivelab.amqp.queue_definition.default.arguments.x_some_too'),
        ], $argumentsDefinition->getArguments());

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_definition.default.arguments.x_some',
            0,
            'x-some'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_definition.default.arguments.x_some',
            1,
            'foo'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_definition.default.arguments.x_some_too',
            0,
            'x-some-too'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_definition.default.arguments.x_some_too',
            1,
            'bar'
        );
    }

    /**
     * Provide queue arguments
     *
     * @return array
     */
    public function provideArguments(): array
    {
        return [
            [
                'dead-letter-exchange',
                'norouted',
                DeadLetterExchangeArgument::class,
            ],

            [
                'dead-letter-routing-key',
                'some',
                DeadLetterRoutingKeyArgument::class,
            ],

            [
                'expires',
                123,
                ExpiresArgument::class,
            ],

            [
                'max-length',
                321,
                MaxLengthArgument::class,
            ],

            [
                'max-length-bytes',
                111,
                MaxLengthBytesArgument::class,
            ],

            [
                'max-priority',
                5,
                MaxPriorityArgument::class,
            ],

            [
                'message-ttl',
                10,
                MessageTtlArgument::class,
            ],

            [
                'overflow',
                'drop-head',
                OverflowArgument::class,
            ],

            [
                'queue-master-locator',
                'min-masters',
                QueueMasterLocatorArgument::class,
            ],

            [
                'queue-mode',
                'lazy',
                QueueModeArgument::class,
            ],

            [
                'queue-type',
                'classic',
                QueueTypeArgument::class,
            ],

            [
                'single-active-consumer',
                true,
                SingleActiveCustomerArgument::class,
                false,
            ],
        ];
    }
}
