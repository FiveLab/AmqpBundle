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
use Symfony\Component\DependencyInjection\Reference;

class AmqpExtensionConfigureChannelsTest extends AbstractExtensionTestCase
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
                'connection1' => [
                    'host'     => 'localhost',
                    'port'     => 5672,
                    'vhost'    => '/',
                    'login'    => 'guest',
                    'password' => 'guest',
                ],

                'connection2' => [
                    'host'     => 'localhost',
                    'port'     => 5672,
                    'vhost'    => '/',
                    'login'    => 'guest',
                    'password' => 'guest',
                ],
            ],
        ];
    }

    /**
     * @test
     */
    public function shouldSuccessConfigureChannels(): void
    {
        $this->load([
            'channels' => [
                'channel1' => [
                    'connection' => 'connection1',
                ],

                'channel2' => [
                    'connection' => 'connection2',
                ],

                'channel3' => [
                    'connection' => 'connection2',
                ],
            ],
        ]);

        // Verify first channel
        $this->assertContainerBuilderHasService('fivelab.amqp.channel_definition.connection1.channel1');
        $this->assertContainerBuilderHasService('fivelab.amqp.channel_factory.connection1.channel1');

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.channel_factory.connection1.channel1',
            0,
            new Reference('fivelab.amqp.connection_factory.connection1')
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.channel_factory.connection1.channel1',
            1,
            new Reference('fivelab.amqp.channel_definition.connection1.channel1')
        );

        // Verify second channel
        $this->assertContainerBuilderHasService('fivelab.amqp.channel_definition.connection2.channel2');
        $this->assertContainerBuilderHasService('fivelab.amqp.channel_factory.connection2.channel2');

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.channel_factory.connection2.channel2',
            0,
            new Reference('fivelab.amqp.connection_factory.connection2')
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.channel_factory.connection2.channel2',
            1,
            new Reference('fivelab.amqp.channel_definition.connection2.channel2')
        );

        // Verify third channel
        $this->assertContainerBuilderHasService('fivelab.amqp.channel_definition.connection2.channel3');
        $this->assertContainerBuilderHasService('fivelab.amqp.channel_factory.connection2.channel3');

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.channel_factory.connection2.channel3',
            0,
            new Reference('fivelab.amqp.connection_factory.connection2')
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'fivelab.amqp.channel_factory.connection2.channel3',
            1,
            new Reference('fivelab.amqp.channel_definition.connection2.channel3')
        );
    }

    /**
     * @test
     */
    public function shouldThrowExceptionIfConnectionWasNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Can\'t configure channel "channel". Connection "somefoobar" was not found.');

        $this->load([
            'channels' => [
                'channel' => [
                    'connection' => 'somefoobar',
                ],
            ],
        ]);
    }
}
