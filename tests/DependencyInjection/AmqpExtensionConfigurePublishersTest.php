<?php

declare(strict_types = 1);

namespace FiveLab\Bundle\AmqpBundle\Tests\DependencyInjection;

use FiveLab\Bundle\AmqpBundle\DependencyInjection\AmqpExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Reference;

class AmqpExtensionConfigurePublishersTest extends AbstractExtensionTestCase
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
        ];
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureWithMinimalConfiguration(): void
    {
        $this->load([
            'publishers' => [
                'some' => [
                    'exchange' => 'default',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.publisher.some');

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.publisher.some',
            0,
            new Reference('fivelab.amqp.exchange_factory.default')
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.publisher.some',
            1,
            new Reference('fivelab.amqp.publisher.some.middlewares')
        );

        $this->assertContainerBuilderHasService('fivelab.amqp.publisher.some.middlewares');

        $middlewaresDefinition = $this->container->getDefinition('fivelab.amqp.publisher.some.middlewares');

        self::assertCount(0, $middlewaresDefinition->getArguments());

        $this->assertContainerBuilderHasParameter('fivelab.amqp.publishers', ['some']);
        $this->assertContainerBuilderHasParameter('fivelab.amqp.savepoint_publishers', []);
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureWithSavepoint(): void
    {
        $this->load([
            'publishers' => [
                'some' => [
                    'exchange'  => 'default',
                    'savepoint' => true,
                ],
            ],
        ]);

        // Check origin service
        $this->assertContainerBuilderHasService('fivelab.amqp.publisher.some.origin');

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.publisher.some.origin',
            0,
            new Reference('fivelab.amqp.exchange_factory.default')
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.publisher.some.origin',
            1,
            new Reference('fivelab.amqp.publisher.some.middlewares')
        );

        $this->assertContainerBuilderHasService('fivelab.amqp.publisher.some.middlewares');

        $middlewaresDefinition = $this->container->getDefinition('fivelab.amqp.publisher.some.middlewares');

        self::assertCount(0, $middlewaresDefinition->getArguments());

        // Check decorator service
        $this->assertContainerBuilderHasService('fivelab.amqp.publisher.some');

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.publisher.some',
            0,
            new Reference('fivelab.amqp.publisher.some.origin')
        );

        $this->assertContainerBuilderHasParameter('fivelab.amqp.publishers', ['some']);
        $this->assertContainerBuilderHasParameter('fivelab.amqp.savepoint_publishers', ['some']);
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureWithMiddlewares(): void
    {
        $this->load([
            'publishers' => [
                'foo' => [
                    'exchange'   => 'default',
                    'middleware' => [
                        'middleware-1',
                        'middleware-2',
                    ],
                ],
            ],

            'publisher_middleware' => [
                'global-middleware-1',
                'global-middleware-2',
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.publisher.foo.middlewares');

        $this->assertContainerBuilderHasService('fivelab.amqp.publisher.foo.middlewares');

        $middlewaresDefinition = $this->container->getDefinition('fivelab.amqp.publisher.foo.middlewares');

        self::assertEquals([
            new Reference('global-middleware-1'),
            new Reference('global-middleware-2'),
            new Reference('middleware-1'),
            new Reference('middleware-2'),
        ], \array_values($middlewaresDefinition->getArguments()));
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureWithOtherChannel(): void
    {
        $this->load([
            'channels' => [
                'foo_channel' => [
                    'connection' => 'default',
                ],
            ],

            'publishers' => [
                'some' => [
                    'exchange' => 'default',
                    'channel'  => 'foo_channel',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.publisher.some');

        // Verify what we create new exchange factory
        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_factory.default.some');

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_factory.default.some',
            0,
            new Reference('fivelab.amqp.channel_factory.default.foo_channel')
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.exchange_factory.default.some',
            1,
            new Reference('fivelab.amqp.exchange_definition.default')
        );
    }

    /**
     * @test
     */
    public function shouldThrowExceptionIfTryToUseUndefinedChannel(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Can\'t configure publisher "some". The channel "foo-bar" was not found.');

        $this->load([
            'publishers' => [
                'some' => [
                    'exchange' => 'default',
                    'channel'  => 'foo-bar',
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
        $this->expectExceptionMessage('Can\'t configure publisher "some". Different connections for exchange and channel. Exchange connection is "default" and channel connection is "custom".');

        $this->load([
            'channels' => [
                'some' => [
                    'connection' => 'custom',
                ],
            ],

            'publishers' => [
                'some' => [
                    'exchange' => 'default',
                    'channel'  => 'some',
                ],
            ],
        ]);
    }
}
