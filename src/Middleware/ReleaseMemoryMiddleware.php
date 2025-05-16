<?php

declare(strict_types = 1);

namespace FiveLab\Bundle\AmqpBundle\Middleware;

use Doctrine\ORM\EntityManagerInterface;
use FiveLab\Component\Amqp\Consumer\Middleware\ConsumerMiddlewareInterface;
use FiveLab\Component\Amqp\Message\ReceivedMessage;
use Symfony\Contracts\Service\ResetInterface;

class ReleaseMemoryMiddleware implements ConsumerMiddlewareInterface
{
    public function __construct(
        private ResetInterface          $servicesResetter,
        private bool                    $clearBeforeMessage = false,
        private ?EntityManagerInterface $entityManager = null,
    ) {
    }

    public function handle(ReceivedMessage $message, callable $next): void
    {
        if (true === $this->clearBeforeMessage) {
            $this->resetMemory();
        }

        try {
            $next($message);
        } finally {
            if (false === $this->clearBeforeMessage) {
                $this->resetMemory();
            }
        }
    }

    private function resetMemory(): void
    {
        $this->servicesResetter->reset();
        $this->entityManager?->clear();
        \gc_collect_cycles();
    }
}
