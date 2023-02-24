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
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

class AmqpExtensionConfigureFromMultipleConfigsTest extends AbstractExtensionTestCase
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
     * @test
     */
    public function shouldSuccessBuildConnections(): void
    {
        $this->doLoad([
            [
                'connections' => [
                    'default' => [
                        'host' => '127.0.0.1',
                    ],
                ],
            ],
            [
                'connections' => [
                    'additional' => [
                        'host' => '127.0.0.2',
                    ],
                ],
            ],
            [
                'connections' => [
                    'some' => [
                        'host' => '127.0.0.3',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.connection_factory.default');
        $this->assertContainerBuilderHasService('fivelab.amqp.connection_factory.additional');
        $this->assertContainerBuilderHasService('fivelab.amqp.connection_factory.some');
    }

    /**
     * @test
     */
    public function shouldSuccessBuildChannels(): void
    {
        $this->doLoad([
            [
                'connections' => [
                    'default' => ['host' => '127.0.0.1'],
                ],

                'channels' => [
                    'primary' => [
                        'connection' => 'default',
                    ],
                ],
            ],
            [
                'channels' => [
                    'second' => [
                        'connection' => 'default',
                    ],
                ],
            ],
            [
                'channels' => [
                    'third' => [
                        'connection' => 'default',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.channel_definition.default.primary');
        $this->assertContainerBuilderHasService('fivelab.amqp.channel_definition.default.second');
        $this->assertContainerBuilderHasService('fivelab.amqp.channel_definition.default.third');
    }

    /**
     * @test
     */
    public function shouldSuccessBuildExchanges(): void
    {
        $this->doLoad([
            [
                'connections' => [
                    'default' => ['host' => '127.0.0.1'],
                ],

                'exchanges' => [
                    'primary' => [
                        'connection' => 'default',
                    ],
                ],
            ],
            [
                'exchanges' => [
                    'second' => [
                        'connection' => 'default',
                    ],
                ],
            ],
            [
                'exchanges' => [
                    'third' => [
                        'connection' => 'default',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_definition.primary');
        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_definition.second');
        $this->assertContainerBuilderHasService('fivelab.amqp.exchange_definition.third');
    }

    /**
     * @test
     */
    public function shouldSuccessBuildQueues(): void
    {
        $this->doLoad([
            [
                'connections' => [
                    'default' => ['host' => '127.0.0.1'],
                ],

                'queues' => [
                    'primary' => [
                        'connection' => 'default',
                    ],
                ],
            ],
            [
                'queues' => [
                    'second' => [
                        'connection' => 'default',
                    ],
                ],
            ],
            [
                'queues' => [
                    'third' => [
                        'connection' => 'default',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.queue_definition.primary');
        $this->assertContainerBuilderHasService('fivelab.amqp.queue_definition.second');
        $this->assertContainerBuilderHasService('fivelab.amqp.queue_definition.third');
    }

    /**
     * @test
     */
    public function shouldSuccessBuildConsumers(): void
    {
        $this->doLoad([
            [
                'connections' => [
                    'default' => ['host' => '127.0.0.1'],
                ],

                'queues' => [
                    'queue' => [
                        'connection' => 'default',
                    ],
                ],

                'consumers' => [
                    'primary' => [
                        'queue'            => 'queue',
                        'message_handlers' => ['foo'],
                    ],
                ],
            ],
            [
                'consumers' => [
                    'second' => [
                        'queue'            => 'queue',
                        'message_handlers' => ['foo'],
                    ],
                ],
            ],
            [
                'consumers' => [
                    'third' => [
                        'queue'            => 'queue',
                        'message_handlers' => ['foo'],
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.primary');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.second');
        $this->assertContainerBuilderHasService('fivelab.amqp.consumer.third');
    }

    /**
     * @test
     */
    public function shouldSuccessBuildPublishers(): void
    {
        $this->doLoad([
            [
                'connections' => [
                    'default' => ['host' => '127.0.0.1'],
                ],

                'exchanges' => [
                    'primary' => [
                        'connection' => 'default',
                    ],
                ],

                'publishers' => [
                    'primary' => [
                        'exchange' => 'primary',
                    ],
                ],
            ],
            [
                'publishers' => [
                    'second' => [
                        'exchange' => 'primary',
                    ],
                ],
            ],
            [
                'publishers' => [
                    'third' => [
                        'exchange' => 'primary',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.publisher.primary');
        $this->assertContainerBuilderHasService('fivelab.amqp.publisher.second');
        $this->assertContainerBuilderHasService('fivelab.amqp.publisher.third');
    }

    /**
     * Override load
     *
     * @param array $configs
     */
    protected function doLoad(array $configs): void
    {
        \array_unshift($configs, [
            'driver' => 'php_extension',
        ]);

        foreach ($this->getContainerExtensions() as $extension) {
            $extension->load($configs, $this->container);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getContainerExtensions(): array
    {
        return [new AmqpExtension()];
    }
}
