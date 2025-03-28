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

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DependencyInjection\Reference;

class AmqpExtensionConfigurePublishersTest extends AmqpExtensionTestCase
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
        ];
    }

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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
