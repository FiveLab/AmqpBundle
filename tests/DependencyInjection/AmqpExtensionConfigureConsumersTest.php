<?php

declare(strict_types = 1);

namespace FiveLab\Bundle\AmqpBundle\Tests\DependencyInjection;

use FiveLab\Bundle\AmqpBundle\DependencyInjection\AmqpExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Reference;

class AmqpExtensionConfigureConsumersTest extends AbstractExtensionTestCase
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
}
