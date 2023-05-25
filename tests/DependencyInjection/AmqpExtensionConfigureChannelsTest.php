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

use FiveLab\Component\Amqp\Channel\ChannelFactoryInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DependencyInjection\Reference;

class AmqpExtensionConfigureChannelsTest extends AmqpExtensionTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getMinimalConfiguration(): array
    {
        return [
            'connections' => [
                'connection1' => [
                    'dsn' => 'amqp://localhost',
                ],

                'connection2' => [
                    'dsn' => 'amqp://localhost',
                ],
            ],
        ];
    }

    #[Test]
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
        $this->assertService('fivelab.amqp.channel_definition.connection1.channel1', '@fivelab.amqp.definition.channel.abstract');

        $this->assertService(
            'fivelab.amqp.channel_factory.connection1.channel1',
            ChannelFactoryInterface::class,
            [new Reference('fivelab.amqp.connection_factory.connection1'), new Reference('fivelab.amqp.channel_definition.connection1.channel1')],
            [new Reference('fivelab.amqp.driver_factory.connection1'), 'createChannelFactory']
        );

        // Verify second channel
        $this->assertService('fivelab.amqp.channel_definition.connection2.channel2', '@fivelab.amqp.definition.channel.abstract');

        $this->assertService(
            'fivelab.amqp.channel_factory.connection2.channel2',
            ChannelFactoryInterface::class,
            [new Reference('fivelab.amqp.connection_factory.connection2'), new Reference('fivelab.amqp.channel_definition.connection2.channel2')],
            [new Reference('fivelab.amqp.driver_factory.connection2'), 'createChannelFactory']
        );

        // Verify third channel
        $this->assertService('fivelab.amqp.channel_definition.connection2.channel3', '@fivelab.amqp.definition.channel.abstract');

        $this->assertService(
            'fivelab.amqp.channel_factory.connection2.channel3',
            ChannelFactoryInterface::class,
            [new Reference('fivelab.amqp.connection_factory.connection2'), new Reference('fivelab.amqp.channel_definition.connection2.channel3')],
            [new Reference('fivelab.amqp.driver_factory.connection2'), 'createChannelFactory']
        );
    }

    #[Test]
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
