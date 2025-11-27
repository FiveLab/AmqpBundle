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

use FiveLab\Component\Amqp\Command\ListConsumersCommand;
use FiveLab\Component\Amqp\Consumer\Checker\ContainerRunConsumerCheckerRegistry;
use FiveLab\Component\Amqp\Consumer\Registry\ContainerConsumerRegistry;
use PHPUnit\Framework\Attributes\Test;
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
        ]);

        // Verify consumer
        $this->assertService('fivelab.amqp.consumer.foo', '@fivelab.amqp.consumer_single.abstract', [
            new Reference('fivelab.amqp.queue_factory.default'),
            new Reference('fivelab.amqp.consumer.foo.message_handler'),
            new Reference('fivelab.amqp.consumer.foo.configuration'),
            new Reference('fivelab.amqp.consumer.foo.strategy'),
        ]);

        // Verify message handler
        $this->assertService('fivelab.amqp.consumer.foo.message_handler', '@fivelab.amqp.consumer.message_handler.abstract', [
            new Reference('handler1'),
            new Reference('handler2'),
        ]);

        // Verify configuration
        $this->assertService('fivelab.amqp.consumer.foo.configuration', '@fivelab.amqp.consumer_single.configuration.abstract', [true, 3, null]);

        // Verify strategy
        $this->assertService('fivelab.amqp.consumer.foo.strategy', '@fivelab.amqp.consumer.strategy.default.abstract', []);

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

        $this->assertService('fivelab.amqp.consumer.foo.strategy', '@fivelab.amqp.consumer.strategy.loop.abstract', [100000, null]);
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

        $this->assertService('fivelab.amqp.consumer.foo.strategy', '@fivelab.amqp.consumer.strategy.loop.abstract', [
            1000000,
            new Reference('tick_handler_service'),
        ]);
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

        $this->assertService('fivelab.amqp.consumer.foo.strategy', '@fivelab.amqp.consumer.strategy.loop.abstract', [
            100000,
            new Reference('tick_handler_service'),
        ]);
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
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.bar.message_handler');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.bar.configuration');

        // Verify consumer
        $this->assertService('fivelab.amqp.consumer.bar', '@fivelab.amqp.consumer_spool.abstract', [
            new Reference('fivelab.amqp.queue_factory.default'),
            new Reference('fivelab.amqp.consumer.bar.message_handler'),
            new Reference('fivelab.amqp.consumer.bar.configuration'),
            new Reference('fivelab.amqp.consumer.bar.strategy'),
        ]);

        // Verify configuration
        $this->assertService('fivelab.amqp.consumer.bar.configuration', '@fivelab.amqp.consumer_spool.configuration.abstract', [
            50,
            30,
            300,
            true,
            null,
        ]);
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

        $this->assertService('fivelab.amqp.consumer.bar.configuration', '@fivelab.amqp.consumer_spool.configuration.abstract', [
            100,
            60,
            120,
            false,
            null,
        ]);
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

        // Verify consumer
        $this->assertService('fivelab.amqp.consumer.bar', '@fivelab.amqp.consumer_loop.abstract', [
            new Reference('fivelab.amqp.queue_factory.default'),
            new Reference('fivelab.amqp.consumer.bar.message_handler'),
            new Reference('fivelab.amqp.consumer.bar.configuration'),
            new Reference('fivelab.amqp.consumer.bar.strategy'),
        ]);

        // Verify configuration
        $this->assertService('fivelab.amqp.consumer.bar.configuration', '@fivelab.amqp.consumer_loop.configuration.abstract', [
            300,
            true,
            3,
            null,
        ]);
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

        // Verify configuration
        $this->assertService('fivelab.amqp.consumer.bar.configuration', '@fivelab.amqp.consumer_loop.configuration.abstract', [
            300,
            true,
            20,
            null,
        ]);
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

        // Verify configuration
        $this->assertService('fivelab.amqp.consumer.bar.configuration', '@fivelab.amqp.consumer_loop.configuration.abstract', [
            60,
            false,
            3,
            null,
        ]);
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
        $this->assertService('fivelab.amqp.queue_factory.default.foo', null, [
            new Reference('fivelab.amqp.channel_factory.default.foo_channel'),
            new Reference('fivelab.amqp.queue_definition.default'),
        ]);
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

        $this->assertService('fivelab.amqp.consumer.bar.configuration', null, [
            true,
            3,
            new Reference('some.foo'),
        ]);
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

        $this->assertService('fivelab.amqp.consumer.bar.configuration', null, [
            300,
            true,
            3,
            new Reference('some.foo'),
        ]);
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

        $this->assertService('fivelab.amqp.consumer.bar.configuration', null, [
            3,
            30,
            300,
            true,
            new Reference('some.foo'),
        ]);
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

        $this->assertService('fivelab.amqp.console_command.list_consumers', ListConsumersCommand::class, [
            ['bar', 'foo'],
        ]);
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
