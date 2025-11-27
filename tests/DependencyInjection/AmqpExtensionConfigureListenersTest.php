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

use FiveLab\Bundle\AmqpBundle\Listener\PingDbalConnectionsListener;
use FiveLab\Bundle\AmqpBundle\Listener\ReleaseMemoryListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\DependencyInjection\Reference;

class AmqpExtensionConfigureListenersTest extends AmqpExtensionTestCase
{
    protected function getMinimalConfiguration(): array
    {
        return [];
    }

    #[Test]
    #[TestWith([true])]
    #[TestWith([false])]
    public function shouldSuccessConfigureReleaseMemoryListener(bool $clearBeforeHandle): void
    {
        $this->load([
            'listeners' => [
                'release_memory' => $clearBeforeHandle,
            ],
        ]);

        $this->assertService(ReleaseMemoryListener::class, ReleaseMemoryListener::class, [
            new Reference('services_resetter'),
            $clearBeforeHandle,
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithTag(ReleaseMemoryListener::class, 'kernel.event_subscriber');
    }

    #[Test]
    #[TestWith([3600])]
    #[TestWith([7200])]
    public function shouldSuccessConfigurePingDbalConnections(int $interval): void
    {
        $this->load([
            'listeners' => [
                'ping_dbal_connections' => $interval,
            ],
        ]);

        $this->assertService(PingDbalConnectionsListener::class, PingDbalConnectionsListener::class, [
            new Reference('doctrine'),
            $interval,
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithTag(PingDbalConnectionsListener::class, 'kernel.event_subscriber');
    }
}
