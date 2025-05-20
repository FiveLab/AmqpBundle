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

use FiveLab\Component\Amqp\Consumer\Checker\ContainerRunConsumerCheckerRegistry;
use FiveLab\Component\Amqp\Consumer\Registry\ContainerConsumerRegistry;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Reference;

class AmqpExtensionConfigureConsumersTest extends AmqpExtensionTestCase
{
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

            'exchanges' => [
                'default' => [
                    'type'       => 'direct',
                    'connection' => 'default',
                ],
            ],

            'queues' => [
                'default' => [
                    'durable'    => true,
                    'name'       => 'test',
                    'connection' => 'default',

                    'bindings' => [
                        ['exchange' => 'default', 'routing' => 'foo'],
                        ['exchange' => 'default', 'routing' => 'bar'],
                    ],
                ],
            ],
        ];
    }

    #[Test]
    public function shouldSuccessConfigureSingleConsumer(): void
    {
        $this->load([
            'connections' => [
                'default' => [
                    'dsn' => 'amqp://localhost',
                ],
            ],

            'consumers' => [
                'foo' => [
                    'queue'            => 'default',
                    'message_handlers' => ['handler1', 'handler2'],
                ],
            ],

            'consumer_middleware' => [
                'middleware1',
                'middleware2',
            ],

            'consumer_event_handlers' => [
                'event_handler_1',
                'event_handler_2',
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.foo');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.foo.middlewares');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.foo.message_handler');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.foo.configuration');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.foo.strategy');

        $definition = $this->container->findDefinition('fivelab.amqp.consumer.foo.configuration');
        $definitionAbstract = $this->container->findDefinition('fivelab.amqp.consumer_single.configuration.abstract');

        // Verify arguments count
        $this->assertEquals(\count($definition->getArguments()), \count($definitionAbstract->getArguments()));

        // Verify consumer
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo', 0, new Reference('fivelab.amqp.queue_factory.default'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo', 1, new Reference('fivelab.amqp.consumer.foo.message_handler'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo', 2, new Reference('fivelab.amqp.consumer.foo.middlewares'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo', 3, new Reference('fivelab.amqp.consumer.foo.configuration'));

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall('fivelab.amqp.consumer.foo', 'addEventHandler', [new ServiceClosureArgument(new Reference('event_handler_1')), true], 0);
        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall('fivelab.amqp.consumer.foo', 'addEventHandler', [new ServiceClosureArgument(new Reference('event_handler_2')), true], 1);

        // Verify consumer class
        $this->assertContainerBuilderHasServiceDefinitionWithParent('fivelab.amqp.consumer.foo', 'fivelab.amqp.consumer_single.abstract');

        // Verify message handler
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.message_handler', 0, new Reference('handler1'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.message_handler', 1, new Reference('handler2'));

        // Verify middleware
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.middlewares', 0, new Reference('middleware1'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.middlewares', 1, new Reference('middleware2'));

        // Verify configuration
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.configuration', 0, true);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.configuration', 1, 3);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.configuration', 2, null);

        // Verify registry
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer_registry', ContainerConsumerRegistry::class);

        $this->assertContainerBuilderHasServiceDefinitionWithServiceLocatorArgument('fivelab.amqp.consumer_registry', 0, [
            'foo' => 'fivelab.amqp.consumer.foo',
        ]);

        // Verify checker registry
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer_checker_registry', ContainerRunConsumerCheckerRegistry::class);
        $this->assertContainerBuilderHasServiceDefinitionWithServiceLocatorArgument('fivelab.amqp.consumer_checker_registry', 0, []);
    }

    #[Test]
    public function shouldSuccessConfigureWithChecker(): void
    {
        $this->load([
            'consumers' => [
                'bla' => [
                    'mode'             => 'single',
                    'queue'            => 'default',
                    'message_handlers' => 'default',
                    'checker'          => 'default_checker',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.consumer_checker_registry', ContainerRunConsumerCheckerRegistry::class);

        $this->assertContainerBuilderHasServiceDefinitionWithServiceLocatorArgument('fivelab.amqp.consumer_checker_registry', 0, [
            'bla' => 'default_checker',
        ]);
    }

    #[Test]
    public function shouldSuccessConfigureSingleConsumerWithNotRequeueOnError(): void
    {
        $this->load([
            'consumers' => [
                'bar' => [
                    'mode'             => 'single',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                    'options'          => [
                        'requeue_on_error' => false,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 0, false);
    }

    #[Test]
    public function shouldSuccessConfigureSingleConsumerWithPrefetchCount(): void
    {
        $this->load([
            'consumers' => [
                'foo' => [
                    'mode'             => 'single',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                    'options'          => [
                        'prefetch_count' => 10,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.configuration', 1, 10);
    }

    #[Test]
    public function shouldSuccessConfigureSingleConsumerWithCustomMiddlewares(): void
    {
        $this->load([
            'consumers' => [
                'foo' => [
                    'message_handlers' => 'handler',
                    'middleware'       => ['middleware3'],
                    'queue'            => 'default',
                ],
            ],

            'consumer_middleware' => [
                'middleware1',
                'middleware2',
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.middlewares', 0, new Reference('middleware1'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.middlewares', 1, new Reference('middleware2'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.middlewares', 2, new Reference('middleware3'));
    }

    #[Test]
    public function shouldSuccessConfigureSingleConsumerWithLoopStrategyAndDefaults(): void
    {
        $this->load([
            'consumers' => [
                'foo' => [
                    'message_handlers' => 'handler',
                    'queue'            => 'default',
                    'strategy'         => 'loop',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithParent('fivelab.amqp.consumer.foo.strategy', 'fivelab.amqp.consumer.strategy.loop.abstract');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.strategy', 0, 100000);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.strategy', 1, null);
    }

    #[Test]
    public function shouldSuccessConfigureSingleConsumerWithLoopStrategyAndParameters(): void
    {
        $this->load([
            'consumers' => [
                'foo' => [
                    'message_handlers' => 'handler',
                    'queue'            => 'default',
                    'strategy'         => 'loop',
                    'tick_handler'     => 'tick_handler_service',
                    'options'          => [
                        'idle_timeout' => 1000000,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithParent('fivelab.amqp.consumer.foo.strategy', 'fivelab.amqp.consumer.strategy.loop.abstract');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.strategy', 0, 1000000);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.strategy', 1, new Reference('tick_handler_service'));
    }

    #[Test]
    public function shouldSuccessConfigureConsumerWithLoopStrategyDeclaredInDefaults(): void
    {
        $this->load([
            'consumer_defaults' => [
                'tick_handler' => 'tick_handler_service',
                'strategy'     => 'loop',
            ],
            'consumers'         => [
                'foo' => [
                    'message_handlers' => 'handler',
                    'queue'            => 'default',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithParent('fivelab.amqp.consumer.foo.strategy', 'fivelab.amqp.consumer.strategy.loop.abstract');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.strategy', 0, 100000);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo.strategy', 1, new Reference('tick_handler_service'));
    }

    #[Test]
    public function shouldSuccessConfigureSpoolConsumer(): void
    {
        $this->load([
            'consumers' => [
                'bar' => [
                    'mode'             => 'spool',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                    'options'          => [
                        'prefetch_count' => 50,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.bar');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.bar.middlewares');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.bar.message_handler');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.bar.configuration');

        $definition = $this->container->findDefinition('fivelab.amqp.consumer.bar.configuration');
        $definitionAbstract = $this->container->findDefinition('fivelab.amqp.consumer_spool.configuration.abstract');

        // Verify arguments count
        $this->assertEquals(\count($definition->getArguments()), \count($definitionAbstract->getArguments()));

        // Verify consumer
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar', 0, new Reference('fivelab.amqp.queue_factory.default'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar', 1, new Reference('fivelab.amqp.consumer.bar.message_handler'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar', 2, new Reference('fivelab.amqp.consumer.bar.middlewares'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar', 3, new Reference('fivelab.amqp.consumer.bar.configuration'));

        // Verify consumer class
        $this->assertContainerBuilderHasServiceDefinitionWithParent('fivelab.amqp.consumer.bar', 'fivelab.amqp.consumer_spool.abstract');

        // Verify configuration
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 0, 50);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 1, 30);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 2, 300);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 3, true);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 4, null);
    }

    #[Test]
    public function shouldSuccessConfigureSpoolConsumerWithCustomConfiguration(): void
    {
        $this->load([
            'consumers' => [
                'bar' => [
                    'mode'             => 'spool',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                    'options'          => [
                        'requeue_on_error' => false,
                        'prefetch_count'   => 100,
                        'timeout'          => 60,
                        'read_timeout'     => 120,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 0, 100);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 1, 60);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 2, 120);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 3, false);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 4, null);
    }

    #[Test]
    public function shouldSuccessConfigureLoopConsumer(): void
    {
        $this->load([
            'consumers' => [
                'bar' => [
                    'mode'             => 'loop',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.bar');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.bar.middlewares');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.bar.message_handler');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.bar.configuration');

        $definition = $this->container->findDefinition('fivelab.amqp.consumer.bar.configuration');
        $definitionAbstract = $this->container->findDefinition('fivelab.amqp.consumer_loop.configuration.abstract');

        // Verify arguments count
        self::assertEquals(\count($definition->getArguments()), \count($definitionAbstract->getArguments()));

        // Verify consumer
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar', 0, new Reference('fivelab.amqp.queue_factory.default'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar', 1, new Reference('fivelab.amqp.consumer.bar.message_handler'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar', 2, new Reference('fivelab.amqp.consumer.bar.middlewares'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar', 3, new Reference('fivelab.amqp.consumer.bar.configuration'));

        // Verify consumer class
        $this->assertContainerBuilderHasServiceDefinitionWithParent('fivelab.amqp.consumer.bar', 'fivelab.amqp.consumer_loop.abstract');

        // Verify configuration
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 0, 300);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 1, true);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 2, 3);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 3, null);
    }

    #[Test]
    public function shouldSuccessConfigureLoopConsumerWithPrefetchCount(): void
    {
        $this->load([
            'consumers' => [
                'bar' => [
                    'mode'             => 'loop',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                    'options'          => [
                        'prefetch_count' => 20,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 2, 20);
    }

    #[Test]
    public function shouldSuccessConfigureLoopConsumerWithCustomConfiguration(): void
    {
        $this->load([
            'consumers' => [
                'bar' => [
                    'mode'             => 'loop',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                    'options'          => [
                        'requeue_on_error' => false,
                        'read_timeout'     => 60,
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 0, 60);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 1, false);
    }

    #[Test]
    public function shouldSuccessConfigureWithCustomChannel(): void
    {
        $this->load([
            'channels' => [
                'foo_channel' => [
                    'connection' => 'default',
                ],
            ],

            'consumers' => [
                'foo' => [
                    'channel'          => 'foo_channel',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.foo');

        // Verify what we create new queue factory for consumer
        $this->assertContainerBuilderHasService('fivelab.amqp.queue_factory.default.foo');

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_factory.default.foo',
            0,
            new Reference('fivelab.amqp.channel_factory.default.foo_channel')
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.queue_factory.default.foo',
            1,
            new Reference('fivelab.amqp.queue_definition.default')
        );
    }

    #[Test]
    public function shouldSuccessConfigureSingleConsumerWithTagNameGenerator(): void
    {
        $this->load([
            'consumers' => [
                'bar' => [
                    'mode'             => 'single',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                    'tag_generator'    => 'some.foo',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 2, new Reference('some.foo'));
    }

    #[Test]
    public function shouldSuccessConfigureLoopConsumerWithTagNameGenerator(): void
    {
        $this->load([
            'consumers' => [
                'bar' => [
                    'mode'             => 'loop',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                    'tag_generator'    => 'some.foo',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 3, new Reference('some.foo'));
    }

    #[Test]
    public function shouldSuccessConfigureSpoolConsumerWithTagNameGenerator(): void
    {
        $this->load([
            'consumers' => [
                'bar' => [
                    'mode'             => 'spool',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                    'tag_generator'    => 'some.foo',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.bar.configuration', 4, new Reference('some.foo'));
    }

    #[Test]
    public function shouldSuccessAddConsumersToListCommand(): void
    {
        $this->load([
            'consumers' => [
                'bar' => [
                    'mode'             => 'loop',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                ],
                'foo' => [
                    'mode'             => 'loop',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.console_command.list_consumers',
            0,
            ['bar', 'foo']
        );
    }

    #[Test]
    public function shouldThrowExceptionIfChannelWasNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Can\'t configure consumer "foo". The channel "foo" was not found.');

        $this->load([
            'consumers' => [
                'foo' => [
                    'channel'          => 'foo',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                ],
            ],
        ]);
    }

    #[Test]
    public function shouldThrowExceptionIfChannelHasDifferentConnection(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Can\'t configure consumer "foo". Different connections for queue and channel. Queue connection is "default" and channel connection is "custom".');

        $this->load([
            'channels' => [
                'channel' => [
                    'connection' => 'custom',
                ],
            ],

            'consumers' => [
                'foo' => [
                    'channel'          => 'channel',
                    'queue'            => 'default',
                    'message_handlers' => 'handler',
                ],
            ],
        ]);
    }
}
