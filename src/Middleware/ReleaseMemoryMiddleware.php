<?php

declare(strict_types = 1);

namespace FiveLab\Bundle\AmqpBundle\Middleware;

use FiveLab\Component\Amqp\Consumer\Middleware\ConsumerMiddlewareInterface;
use FiveLab\Component\Amqp\Message\ReceivedMessage;
use Symfony\Contracts\Service\ResetInterface;

class ReleaseMemoryMiddleware implements ConsumerMiddlewareInterface
{
    public function __construct(
        private ResetInterface          $servicesResetter,
        private bool                    $clearBeforeHandle = false
    ) {
    }

    public function handle(ReceivedMessage $message, callable $next): void
    {
        if (true === $this->clearBeforeHandle) {
            $this->resetMemory();
        }

        try {
            $next($message);
        } finally {
            if (false === $this->clearBeforeHandle) {
                $this->resetMemory();
            }
        }
    }

    private function resetMemory(): void
    {
        $this->servicesResetter->reset();

        \gc_collect_cycles();
    }
}
