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

use FiveLab\Bundle\AmqpBundle\DependencyInjection\AmqpExtension;
use FiveLab\Component\Amqp\Consumer\Registry\ContainerConsumerRegistry;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Reference;

class AmqpExtensionConfigureConsumersTest extends AbstractExtensionTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->setParameter('kernel.debug', false);
    }

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

    /**
     * @test
     */
    public function shouldSuccessConfigureSingleConsumer(): void
    {
        $this->load([
            'connections' => [
                'default' => [
                    'host' => 'localhost',
                    'port' => 5672,
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
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.foo');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.foo.middlewares');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.foo.message_handler');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.foo.configuration');

        $definition = $this->container->findDefinition('fivelab.amqp.consumer.foo.configuration');
        $definitionAbstract = $this->container->findDefinition('fivelab.amqp.consumer_single.configuration.abstract');

        // Verify arguments count
        $this->assertEquals(\count($definition->getArguments()), \count($definitionAbstract->getArguments()));

        // Verify consumer
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo', 0, new Reference('fivelab.amqp.queue_factory.default'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo', 1, new Reference('fivelab.amqp.consumer.foo.message_handler'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo', 2, new Reference('fivelab.amqp.consumer.foo.middlewares'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.consumer.foo', 3, new Reference('fivelab.amqp.consumer.foo.configuration'));

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
    }

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
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
