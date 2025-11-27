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

namespace FiveLab\Bundle\AmqpBundle\Tests\Listener;

use FiveLab\Bundle\AmqpBundle\Listener\ReleaseMemoryListener;
use FiveLab\Component\Amqp\AmqpEvents;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ResetInterface;

class ReleaseMemoryListenerTest extends TestCase
{
    #[Test]
    public function shouldSuccessGetListeners(): void
    {
        self::assertEquals([
            AmqpEvents::RECEIVE_MESSAGE   => ['onReceiveMessage', 1024],
            AmqpEvents::PROCESSED_MESSAGE => ['onProcessedMessage', -1024],
        ], ReleaseMemoryListener::getSubscribedEvents());
    }

    #[Test]
    public function shouldSuccessClearBeforeHandle(): void
    {
        $resetter = $this->createMock(ResetInterface::class);
        $reset = false;

        $resetter->expects($this->once())
            ->method('reset')
            ->willReturnCallback(static function () use (&$reset): void {
                $reset = true;
            });

        $listener = new ReleaseMemoryListener($resetter, true);

        $listener->onReceiveMessage();
        self::assertTrue($reset);
        $listener->onProcessedMessage();
    }

    #[Test]
    public function shouldSuccessClearAfterHandle(): void
    {
        $resetter = $this->createMock(ResetInterface::class);
        $reset = false;

        $resetter->expects($this->once())
            ->method('reset')
            ->willReturnCallback(static function () use (&$reset): void {
                $reset = true;
            });

        $listener = new ReleaseMemoryListener($resetter, false);

        $listener->onReceiveMessage();
        self::assertFalse($reset);
        $listener->onProcessedMessage();
        self::assertTrue($reset);
    }
}
