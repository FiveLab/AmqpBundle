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
use Symfony\Component\DependencyInjection\ServiceLocator;

class AmqpExtensionConfigureDelaysTest extends AmqpExtensionTestCase
{
    protected function getMinimalConfiguration(): array
    {
        return [
            'connections' => [
                'connection' => [
                    'dsn' => 'amqp://localhost',
                ],
            ],

            'channels' => [
                'channel' => [
                    'connection' => 'connection',
                ],
            ],
        ];
    }

    #[Test]
    public function shouldSuccessConfigureDelay(): void
    {
        $this->load([
            'delay' => [
                'connection'    => 'connection',
                'exchange'      => 'delay',
                'expired_queue' => 'delay.expired',
                'consumer_key'  => 'delay_expired',
                'delays'        => [
                    '5second' => [
                        'ttl'     => 5000,
                        'queue'   => 'delay.5second',
                        'routing' => '5sec',
                    ],
                ],
            ],
        ]);

        // Check delay exchanges
        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_definition.delay');
        $exchangeDefinition = $this->container->getDefinition('fivelab.amqp.exchange_definition.delay');

        self::assertEquals('delay', $exchangeDefinition->getArgument(0));
        self::assertEquals('direct', $exchangeDefinition->getArgument(1));
        self::assertEquals(true, $exchangeDefinition->getArgument(2));
        self::assertEquals(false, $exchangeDefinition->getArgument(3));
        self::assertNull($exchangeDefinition->getArgument(4));

        // Check expired queue
        $this->assertContainerBuilderHasService('fivelab.amqp.queue_definition.delay.expired');
        $expiredQueueDefinition = $this->container->getDefinition('fivelab.amqp.queue_definition.delay.expired');

        self::assertEquals([
            'delay.expired',
            new Reference('fivelab.amqp.queue_definition.delay.expired.bindings'),
            new Reference('fivelab.amqp.queue_definition.delay.expired.unbindings'),
            true,
            false,
            false,
            false,
            null,
        ], \array_values($expiredQueueDefinition->getArguments()));

        self::assertEquals([
            new Reference('fivelab.amqp.queue_definition.delay.expired.binding.delay_message.expired'),
        ], $this->container->getDefinition('fivelab.amqp.queue_definition.delay.expired.bindings')->getArguments());

        self::assertEquals([], $this->container->getDefinition('fivelab.amqp.queue_definition.delay.expired.unbindings')->getArguments());

        self::assertEquals([
            'delay',
            'message.expired',
        ], \array_values($this->container->getDefinition('fivelab.amqp.queue_definition.delay.expired.binding.delay_message.expired')->getArguments()));

        // Check landfill queue
        $this->assertContainerBuilderHasService('fivelab.amqp.queue_definition.delay.5second');
        $queueDefinition = $this->container->getDefinition('fivelab.amqp.queue_definition.delay.5second');

        self::assertEquals([
            'delay.5second',
            new Reference('fivelab.amqp.queue_definition.delay.5second.bindings'),
            new Reference('fivelab.amqp.queue_definition.delay.5second.unbindings'),
            true,
            false,
            false,
            false,
            new Reference('fivelab.amqp.queue_definition.delay.5second.arguments'),
        ], \array_values($queueDefinition->getArguments()));

        self::assertEquals([
            new Reference('fivelab.amqp.queue_definition.delay.5second.binding.delay_5sec'),
        ], $this->container->getDefinition('fivelab.amqp.queue_definition.delay.5second.bindings')->getArguments());

        self::assertEquals([], $this->container->getDefinition('fivelab.amqp.queue_definition.delay.5second.unbindings')->getArguments());

        self::assertEquals([
            'delay',
            '5sec',
        ], \array_values($this->container->getDefinition('fivelab.amqp.queue_definition.delay.5second.binding.delay_5sec')->getArguments()));

        self::assertEquals([
            new Reference('fivelab.amqp.queue_definition.delay.5second.arguments.dead_letter_exchange'),
            new Reference('fivelab.amqp.queue_definition.delay.5second.arguments.dead_letter_routing_key'),
            new Reference('fivelab.amqp.queue_definition.delay.5second.arguments.message_ttl'),
        ], \array_values($this->container->getDefinition('fivelab.amqp.queue_definition.delay.5second.arguments')->getArguments()));

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.queue_definition.delay.5second.arguments.dead_letter_exchange', 0, 'delay');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.queue_definition.delay.5second.arguments.dead_letter_routing_key', 0, 'message.expired');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('fivelab.amqp.queue_definition.delay.5second.arguments.message_ttl', 0, 5000);

        // Check publisher
        $this->assertContainerBuilderHasService('fivelab.amqp.publisher.5second');

        self::assertEquals([
            new Reference('fivelab.amqp.exchange_factory.delay'),
            new Reference('fivelab.amqp.publisher.5second.middlewares'),
        ], \array_values($this->container->getDefinition('fivelab.amqp.publisher.5second')->getArguments()));

        $this->assertContainerBuilderHasService('fivelab.amqp.publisher.5second.delay');

        self::assertEquals([
            new Reference('fivelab.amqp.publisher.5second.delay.inner'),
            '5sec',
        ], \array_values($this->container->getDefinition('fivelab.amqp.publisher.5second.delay')->getArguments()));

        self::assertEquals([
            'fivelab.amqp.publisher.5second',
            null,
            0,
        ], $this->container->getDefinition('fivelab.amqp.publisher.5second.delay')->getDecoratedService());

        // Check message handler
        $this->assertContainerBuilderHasService('fivelab.amqp.delay.message_handler.5second');

        self::assertEquals([
            'index_1' => new Reference('fivelab.amqp.publisher.5second.delay.inner'),
            'index_2' => '5sec',
        ], $this->container->getDefinition('fivelab.amqp.delay.message_handler.5second')->getArguments());

        // Check consumer for handle expired messages
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.delay_expired');

        self::assertEquals([
            new Reference('fivelab.amqp.queue_factory.delay.expired'),
            new Reference('fivelab.amqp.consumer.delay_expired.message_handler'),
            new Reference('fivelab.amqp.consumer.delay_expired.middlewares'),
            new Reference('fivelab.amqp.consumer.delay_expired.configuration'),
            new Reference('fivelab.amqp.consumer.delay_expired.strategy'),
        ], \array_values($this->container->getDefinition('fivelab.amqp.consumer.delay_expired')->getArguments()));

        self::assertEquals([
            new Reference('fivelab.amqp.delay.message_handler.5second'),
        ], \array_values($this->container->getDefinition('fivelab.amqp.consumer.delay_expired.message_handler')->getArguments()));
    }

    #[Test]
    public function shouldSuccessConfigureDelaysWithOtherConsumers(): void
    {
        $this->load([
            'queues' => [
                'foo' => [
                    'connection' => 'connection',
                ],
            ],

            'consumers' => [
                'foo' => [
                    'queue'            => 'foo',
                    'message_handlers' => 'handler.foo',
                    'checker'          => 'foo.checker',
                ],
            ],

            'delay' => [
                'connection'    => 'connection',
                'exchange'      => 'delay',
                'expired_queue' => 'delay.expired',
                'consumer_key'  => 'delay_expired',
                'delays'        => [
                    '5second' => [
                        'ttl'     => 5000,
                        'queue'   => 'delay.5second',
                        'routing' => '5sec',
                    ],
                ],
            ],
        ]);

        $this->assertService('fivelab.amqp.consumer_registry', ContainerConsumerRegistry::class);
        $consumerRegistryLocatorId = (string) $this->container->getDefinition('fivelab.amqp.consumer_registry')->getArgument(0);

        $this->assertService(
            $consumerRegistryLocatorId,
            ServiceLocator::class,
            [
                [
                    'foo'           => new ServiceClosureArgument(new Reference('fivelab.amqp.consumer.foo')),
                    'delay_expired' => new ServiceClosureArgument(new Reference('fivelab.amqp.consumer.delay_expired')),
                ],
            ]
        );

        $this->assertService('fivelab.amqp.consumer_checker_registry', ContainerRunConsumerCheckerRegistry::class);
        $checkerRegistryLocatorId = (string) $this->container->getDefinition('fivelab.amqp.consumer_checker_registry')->getArgument(0);

        $this->assertService(
            $checkerRegistryLocatorId,
            ServiceLocator::class,
            [
                [
                    'foo' => new ServiceClosureArgument(new Reference('foo.checker')),
                ],
            ]
        );
    }
}
