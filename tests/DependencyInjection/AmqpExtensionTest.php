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

use FiveLab\Bundle\AmqpBundle\Connection\Registry\ConnectionFactoryRegistryInterface;
use FiveLab\Component\Amqp\Consumer\Registry\ConsumerRegistryInterface;
use FiveLab\Component\Amqp\Exchange\Registry\ExchangeFactoryRegistryInterface;
use FiveLab\Component\Amqp\Publisher\Registry\PublisherRegistryInterface;
use FiveLab\Component\Amqp\Queue\Registry\QueueFactoryRegistryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\DependencyInjection\Reference;

class AmqpExtensionTest extends AmqpExtensionTestCase
{
    #[Test]
    public function shouldSuccessProcessWithoutConfiguration(): void
    {
        $this->load([]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function shouldSuccessConfigureRoundRobin(): void
    {
        $this->load([
            'connections' => [
                'default' => [
                    'dsn' => 'amqp://host',
                ],
            ],

            'round_robin' => [
                'enabled'                        => true,
                'executes_messages_per_consumer' => 50,
                'consumers_read_timeout'         => 5.0,
            ],

            'queues' => [
                'foo' => [
                    'connection' => 'default',
                ],

                'bar' => [
                    'connection' => 'default',
                ],
            ],

            'consumers' => [
                'foo' => [
                    'queue'            => 'foo',
                    'message_handlers' => ['handler'],
                ],

                'bar' => [
                    'queue'            => 'bar',
                    'message_handlers' => ['handler'],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithTag('fivelab.amqp.console_command.run_round_robin_consumer', 'console.command');
        $this->assertContainerBuilderHasService('fivelab.amqp.round_robin_consumer');
        $this->assertContainerBuilderHasService('fivelab.amqp.round_robin_consumer.configuration');

        $command = $this->container->getDefinition('fivelab.amqp.console_command.run_round_robin_consumer');
        self::assertEquals(new Reference('fivelab.amqp.round_robin_consumer'), $command->getArgument(0));

        $configuration = $this->container->getDefinition('fivelab.amqp.round_robin_consumer.configuration');
        self::assertEquals(50, $configuration->getArgument(0));
        self::assertEquals(5.0, $configuration->getArgument(1));
        self::assertEquals(0, $configuration->getArgument(2));

        $consumer = $this->container->getDefinition('fivelab.amqp.round_robin_consumer');

        self::assertEquals([
            new Reference('fivelab.amqp.round_robin_consumer.configuration'),
            new Reference('fivelab.amqp.consumer_registry'),
            ['foo', 'bar'],
        ], $consumer->getArguments());
    }

    #[Test]
    public function shouldSuccessNoConfigureRoundRobin(): void
    {
        $this->load([
            'round_robin' => [
                'enabled' => false,
            ],
        ]);

        $this->assertContainerBuilderNotHasService('fivelab.amqp.round_robin_consumer');
    }

    #[Test]
    #[TestWith(['fivelab.amqp.consumer_registry'])]
    #[TestWith(['fivelab.amqp.exchange_factory_registry'])]
    #[TestWith(['fivelab.amqp.queue_factory_registry'])]
    #[TestWith(['fivelab.amqp.connection_factory_registry'])]
    #[TestWith(['fivelab.amqp.publisher_registry'])]
    public function shouldRegistriesIsPublic(string $registry): void
    {
        $this->load([]);

        $definition = $this->container->getDefinition($registry);

        self::assertTrue($definition->isPublic(), \sprintf(
            'The service "%s" must be public.',
            $registry
        ));
    }

    #[Test]
    #[TestWith([ConsumerRegistryInterface::class, 'fivelab.amqp.consumer_registry'])]
    #[TestWith([ExchangeFactoryRegistryInterface::class, 'fivelab.amqp.queue_factory_registry'])]
    #[TestWith([QueueFactoryRegistryInterface::class, 'fivelab.amqp.queue_factory_registry'])]
    #[TestWith([ConnectionFactoryRegistryInterface::class, 'fivelab.amqp.connection_factory_registry'])]
    #[TestWith([PublisherRegistryInterface::class, 'fivelab.amqp.publisher_registry'])]
    public function shouldRegistriesHasAlias(string $alias, string $reference): void
    {
        $this->load([]);

        $this->assertContainerBuilderHasAlias($alias, $reference);
    }
}
