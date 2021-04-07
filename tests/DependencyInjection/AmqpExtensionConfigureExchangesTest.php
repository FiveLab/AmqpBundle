<?php

declare(strict_types = 1);

namespace FiveLab\Bundle\AmqpBundle\Tests\DependencyInjection;

use FiveLab\Bundle\AmqpBundle\DependencyInjection\AmqpExtension;
use FiveLab\Component\Amqp\Exchange\Definition\Arguments\AlternateExchangeArgument;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

class AmqpExtensionConfigureExchangesTest extends AbstractExtensionTestCase
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
    public function shouldSuccessConfigureExchanges(): void
    {
        $this->load([
            'exchanges' => [
                'direct_durable' => [
                    'connection' => 'default',
                    'type'       => 'direct',
                    'durable'    => true,
                    'passive'    => false,
                ],

                'topic_passive' => [
                    'connection' => 'custom',
                    'type'       => 'topic',
                    'durable'    => false,
                    'passive'    => true,
                ],
            ],
        ]);

        // Check definitions
        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_definition.direct_durable');
        $directDurableDefinition = $this->container->getDefinition('fivelab.amqp.exchange_definition.direct_durable');

        self::assertEquals('direct_durable', $directDurableDefinition->getArgument(0));
        self::assertEquals('direct', $directDurableDefinition->getArgument(1));
        self::assertEquals(true, $directDurableDefinition->getArgument(2));
        self::assertEquals(false, $directDurableDefinition->getArgument(3));
        self::assertEquals(null, $directDurableDefinition->getArgument(4));

        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_definition.topic_passive');
        $topicPassiveDefinition = $this->container->getDefinition('fivelab.amqp.exchange_definition.topic_passive');

        self::assertEquals('topic_passive', $topicPassiveDefinition->getArgument(0));
        self::assertEquals('topic', $topicPassiveDefinition->getArgument(1));
        self::assertEquals(false, $topicPassiveDefinition->getArgument(2));
        self::assertEquals(true, $topicPassiveDefinition->getArgument(3));
        self::assertEquals(null, $topicPassiveDefinition->getArgument(4));

        // Check factories for direct exchange
        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_factory.direct_durable');

        // Check factories for topic exchange
        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_factory.topic_passive');

        // Check parameter
        $this->assertContainerBuilderHasParameter('fivelab.amqp.exchange_factories', [
            'direct_durable',
            'topic_passive',
        ]);

        // @todo: check connections
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureWithExpressionLanguage(): void
    {
        $this->load([
            'exchanges' => [
                'test' => [
                    'connection' => 'default',
                    'type'       => 'direct',
                    'passive'    => '@=container.getEnv("BAR")',
                ]
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_definition.test');
        $testDefinition = $this->container->getDefinition('fivelab.amqp.exchange_definition.test');

        self::assertEquals(new Expression('container.getEnv("BAR")'), $testDefinition->getArgument(3));
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureDefaultExchange(): void
    {
        $this->load([
            'exchanges' => [
                'default' => [
                    'connection' => 'default',
                    'name'       => 'amq.default',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_definition.default');

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.exchange_definition.default', 0, '');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.exchange_definition.default', 1, 'direct');
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureExchangesWithArguments(): void
    {
        $this->load([
            'exchanges' => [
                'direct' => [
                    'connection' => 'default',
                    'type'       => 'direct',
                    'arguments'  => [
                        'alternate-exchange' => 'some',
                        'custom'             => [
                            'x-my-custom-argument' => 'foo',
                            'x-my-some-argument'   => 'bar',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_definition.direct');
        $directDefinition = $this->container->getDefinition('fivelab.amqp.exchange_definition.direct');

        self::assertEquals(new Reference('fivelab.amqp.exchange_definition.direct.arguments'), $directDefinition->getArgument(4));
        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_definition.direct.arguments');

        // Verify argument definitions
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.direct.arguments',
            0,
            new Reference('fivelab.amqp.exchange_definition.direct.arguments.alternate_exchange')
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.direct.arguments',
            1,
            new Reference('fivelab.amqp.exchange_definition.direct.arguments.x_my_custom_argument')
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.direct.arguments',
            2,
            new Reference('fivelab.amqp.exchange_definition.direct.arguments.x_my_some_argument')
        );

        // Verify argument values
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.direct.arguments.alternate_exchange',
            0,
            'some'
        );

        self::assertEquals(
            AlternateExchangeArgument::class,
            $this->container->getDefinition('fivelab.amqp.exchange_definition.direct.arguments.alternate_exchange')->getClass()
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.direct.arguments.x_my_custom_argument',
            0,
            'x-my-custom-argument'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.direct.arguments.x_my_custom_argument',
            1,
            'foo'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.direct.arguments.x_my_some_argument',
            0,
            'x-my-some-argument'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.direct.arguments.x_my_some_argument',
            1,
            'bar'
        );
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureWithBindings(): void
    {
        $this->load([
            'exchanges' => [
                'default' => [
                    'connection' => 'default',
                    'type'       => 'direct',
                    'bindings'   => [
                        ['exchange' => 'foo', 'routing' => 'foo.some'],
                        ['exchange' => 'bar', 'routing' => 'bar.some'],
                    ],
                ],
            ],
        ]);

        $queueDefinition = $this->container->getDefinition('fivelab.amqp.exchange_definition.default');

        self::assertEquals(new Reference('fivelab.amqp.exchange_definition.default.bindings'), $queueDefinition->getArgument(5));

        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_definition.default.bindings');

        self::assertEquals([
            new Reference('fivelab.amqp.exchange_definition.default.binding.foo_foo.some'),
            new Reference('fivelab.amqp.exchange_definition.default.binding.bar_bar.some'),
        ], \array_values($this->container->getDefinition('fivelab.amqp.exchange_definition.default.bindings')->getArguments()));

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.default.binding.foo_foo.some',
            0,
            'foo'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.default.binding.foo_foo.some',
            1,
            'foo.some'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.default.binding.bar_bar.some',
            0,
            'bar'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.default.binding.bar_bar.some',
            1,
            'bar.some'
        );
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureWithUnBindings(): void
    {
        $this->load([
            'exchanges' => [
                'default' => [
                    'connection' => 'default',
                    'type'       => 'direct',
                    'unbindings' => [
                        ['exchange' => 'foo', 'routing' => 'foo.some'],
                        ['exchange' => 'bar', 'routing' => 'bar.some'],
                    ],
                ],
            ],
        ]);

        $queueDefinition = $this->container->getDefinition('fivelab.amqp.exchange_definition.default');

        self::assertEquals(new Reference('fivelab.amqp.exchange_definition.default.unbindings'), $queueDefinition->getArgument(6));

        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_definition.default.unbindings');

        self::assertEquals([
            new Reference('fivelab.amqp.exchange_definition.default.unbinding.foo_foo.some'),
            new Reference('fivelab.amqp.exchange_definition.default.unbinding.bar_bar.some'),
        ], \array_values($this->container->getDefinition('fivelab.amqp.exchange_definition.default.unbindings')->getArguments()));

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.default.unbinding.foo_foo.some',
            0,
            'foo'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.default.unbinding.foo_foo.some',
            1,
            'foo.some'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.default.unbinding.bar_bar.some',
            0,
            'bar'
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_definition.default.unbinding.bar_bar.some',
            1,
            'bar.some'
        );
    }

    /**
     * @test
     */
    public function shouldFailConfigureExchangesWithInvalidType(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "fivelab_amqp.exchanges.default.type": Invalid exchange type ""foo"".');

        $this->load([
            'exchanges' => [
                'default' => [
                    'connection' => 'default',
                    'type'       => 'foo',
                ],
            ],
        ]);
    }
}
