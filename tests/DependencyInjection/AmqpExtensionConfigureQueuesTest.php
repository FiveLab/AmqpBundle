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

use FiveLab\Bundle\AmqpBundle\Tests\TestEnum;
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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

class AmqpExtensionConfigureQueuesTest extends AmqpExtensionTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getMinimalConfiguration(): array
    {
        return [
            'connections' => [
                'default' => [
                    'dsn' => 'amqp://host1',
                ],

                'custom' => [
                    'dsn' => 'amqp://custom',
                ],
            ],
        ];
    }

    #[Test]
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
            new Reference('fivelab.amqp.queue_definition.default.bindings'),
            new Reference('fivelab.amqp.queue_definition.default.unbindings'),
            true,
            false,
            false,
            false,
            null,
        ], \array_values($queueDefinition->getArguments()));
    }

    #[Test]
    public function shouldSuccessConfigureWithExpressionLanguage(): void
    {
        $this->load([
            'queues' => [
                'default' => [
                    'connection' => 'default',
                    'passive'    => '@=container.getEnv("FOO")',
                ],
            ],
        ]);

        $queueDefinition = $this->container->getDefinition('fivelab.amqp.queue_definition.default');

        self::assertEquals(new Expression('container.getEnv("FOO")'), $queueDefinition->getArgument(4));
    }

    #[Test]
    public function shouldSuccessConfigureWithMultipleAndExistParameterForAllQueues(): void
    {
        $this->load([
            'queues' => [
                'first'  => [
                    'connection' => 'default',
                ],
                'second' => [
                    'connection' => 'default',
                ],
                'third'  => [
                    'connection' => 'default',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasParameter('fivelab.amqp.queue_factories', [
            'first',
            'second',
            'third',
        ]);
    }

    #[Test]
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
            new Reference('fivelab.amqp.queue_definition.default.bindings'),
            new Reference('fivelab.amqp.queue_definition.default.unbindings'),
            false,
            true,
            true,
            true,
            null,
        ], \array_values($queueDefinition->getArguments()));
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function shouldSuccessConfigureWithBindingsAsEnum(): void
    {
        $this->load([
            'queues' => [
                'default' => [
                    'connection' => 'default',
                    'bindings'   => [
                        ['exchange' => TestEnum::Test, 'routing' => TestEnum::Foo],
                    ],
                    'unbindings' => [
                        ['exchange' => TestEnum::Test, 'routing' => TestEnum::Bar],
                    ],
                ],
            ],
        ]);

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
    }

    #[Test]
    #[TestWith(['dead-letter-exchange', 'norouted', DeadLetterExchangeArgument::class])]
    #[TestWith(['dead-letter-routing-key', 'some', DeadLetterRoutingKeyArgument::class])]
    #[TestWith(['expires', 123, ExpiresArgument::class])]
    #[TestWith(['max-length', 321, MaxLengthArgument::class])]
    #[TestWith(['max-length-bytes', 111, MaxLengthBytesArgument::class])]
    #[TestWith(['max-priority', 5, MaxPriorityArgument::class])]
    #[TestWith(['message-ttl', 10, MessageTtlArgument::class])]
    #[TestWith(['overflow', 'drop-head', OverflowArgument::class])]
    #[TestWith(['queue-master-locator', 'min-masters', QueueMasterLocatorArgument::class])]
    #[TestWith(['queue-mode', 'lazy', QueueModeArgument::class])]
    #[TestWith(['queue-type', 'classic', QueueTypeArgument::class])]
    #[TestWith(['single-active-consumer', true, SingleActiveCustomerArgument::class, false])]
    public function shouldSuccessConfigureWithArguments(string $argumentName, mixed $argumentValue, string $expectedAggumentClass, bool $checkValue = true): void
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

    #[Test]
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

    #[Test]
    public function shouldSuccessConfigureWithDefaultArguments(): void
    {
        $this->load([
            'queue_default_arguments' => [
                'queue-type' => 'quorum',
            ],

            'queues' => [
                'default' => [
                    'connection' => 'default',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.queue_definition.default.arguments');
        $argumentsDefinition = $this->container->getDefinition('fivelab.amqp.queue_definition.default.arguments');

        self::assertEquals([
            new Reference('fivelab.amqp.queue_definition.default.arguments.queue_type'),
        ], $argumentsDefinition->getArguments());

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_definition.default.arguments.queue_type',
            0,
            'quorum'
        );
    }

    #[Test]
    public function shouldSuccessConfigureWithDefaultArgumentsAndCorrectInherited(): void
    {
        $this->load([
            'queue_default_arguments' => [
                'queue-type' => 'classic',
            ],

            'queues' => [
                'default' => [
                    'connection' => 'default',
                    'arguments'  => [
                        'queue-type'             => 'quorum',
                        'single-active-consumer' => true,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.queue_definition.default.arguments');
        $argumentsDefinition = $this->container->getDefinition('fivelab.amqp.queue_definition.default.arguments');

        self::assertEquals([
            new Reference('fivelab.amqp.queue_definition.default.arguments.queue_type'),
            new Reference('fivelab.amqp.queue_definition.default.arguments.single_active_consumer'),
        ], $argumentsDefinition->getArguments());

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_definition.default.arguments.queue_type',
            0,
            'quorum'
        );

        $this->assertContainerBuilderHasService('fivelab.amqp.queue_definition.default.arguments.single_active_consumer');
    }
}
