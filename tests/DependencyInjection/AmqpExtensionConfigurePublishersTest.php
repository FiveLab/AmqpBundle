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
}
