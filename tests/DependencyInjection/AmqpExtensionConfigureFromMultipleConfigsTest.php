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

class AmqpExtensionConfigureFromMultipleConfigsTest extends AmqpExtensionTestCase
{
    #[Test]
    public function shouldSuccessBuildConnections(): void
    {
        $this->doLoad([
            [
                'connections' => [
                    'default' => [
                        'dsn' => 'amqp://127.0.0.1',
                    ],
                ],
            ],
            [
                'connections' => [
                    'additional' => [
                        'dsn' => 'amqp://127.0.0.2',
                    ],
                ],
            ],
            [
                'connections' => [
                    'some' => [
                        'dsn' => 'amqp://127.0.0.3',
                    ],
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService('fivelab.amqp.connection_factory.default');
        $this->assertContainerBuilderHasService('fivelab.amqp.connection_factory.additional');
        $this->assertContainerBuilderHasService('fivelab.amqp.connection_factory.some');
    }

    #[Test]
    public function shouldSuccessBuildChannels(): void
    {
        $this->doLoad([
            [
                'connections' => [
                    'default' => ['dsn' => 'amqp://127.0.0.1'],
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

    #[Test]
    public function shouldSuccessBuildExchanges(): void
    {
        $this->doLoad([
            [
                'connections' => [
                    'default' => ['dsn' => 'amqp://127.0.0.1'],
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

    #[Test]
    public function shouldSuccessBuildQueues(): void
    {
        $this->doLoad([
            [
                'connections' => [
                    'default' => ['dsn' => 'amqp://127.0.0.1'],
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

    #[Test]
    public function shouldSuccessBuildConsumers(): void
    {
        $this->doLoad([
            [
                'connections' => [
                    'default' => ['dsn' => 'amqp://127.0.0.1'],
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

    #[Test]
    public function shouldSuccessBuildPublishers(): void
    {
        $this->doLoad([
            [
                'connections' => [
                    'default' => ['dsn' => 'amqp://127.0.0.1'],
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
        foreach ($this->getContainerExtensions() as $extension) {
            $extension->load($configs, $this->container);
        }
    }
}
