<?php

declare(strict_types = 1);

namespace FiveLab\Bundle\AmqpBundle\Tests\Middleware;

use FiveLab\Bundle\AmqpBundle\Middleware\ReleaseMemoryMiddleware;
use FiveLab\Component\Amqp\Message\ReceivedMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ResetInterface;

class ReleaseMemoryMiddlewareTest extends TestCase
{
    private ResetInterface $servicesResetter;

    protected function setUp(): void
    {
        $this->servicesResetter = $this->createMock(ResetInterface::class);
    }

    #[Test]
    public function clearMemoryBeforeMessage(): void
    {
        $alreadyReset = false;
        $message = $this->createMock(ReceivedMessage::class);

        $next = function (ReceivedMessage $message) use (&$alreadyReset) {
            $this->assertTrue($alreadyReset);
        };

        $this->servicesResetter
            ->expects($this->once())
            ->method('reset')
            ->willReturnCallback(function () use (&$alreadyReset): void {
            $alreadyReset = true;
        });;

        $this->getMiddleware(true)->handle($message, $next);
    }

    #[Test]
    public function clearMemoryOnlyAfterMessage(): void
    {
        $alreadyReset = false;
        $message = $this->createMock(ReceivedMessage::class);

        $next = function (ReceivedMessage $message) use (&$alreadyReset) {
            $this->assertFalse($alreadyReset);
        };

        $this->servicesResetter
            ->expects($this->once())
            ->method('reset')
            ->willReturnCallback(function () use (&$alreadyReset): void {
                $alreadyReset = true;
            });;

        $this->getMiddleware(false)->handle($message, $next);
    }

    private function getMiddleware(bool $clearBeforeMessage): ReleaseMemoryMiddleware
    {
        return new ReleaseMemoryMiddleware(
            $this->servicesResetter,
            $clearBeforeMessage
        );
    }
}
