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

namespace FiveLab\Bundle\AmqpBundle\Listener;

use FiveLab\Component\Amqp\Event\ProcessedMessageEvent;
use FiveLab\Component\Amqp\Event\ReceiveMessageEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

readonly class ReleaseMemoryListener implements EventSubscriberInterface
{
    public function __construct(private ResetInterface $resetter, private bool $clearBeforeHandle = false)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReceiveMessageEvent::class   => ['onReceiveMessage', 1024],
            ProcessedMessageEvent::class => ['onProcessedMessage', -1024],
        ];
    }

    public function onReceiveMessage(): void
    {
        if ($this->clearBeforeHandle) {
            $this->resetMemory();
        }
    }

    public function onProcessedMessage(): void
    {
        if (!$this->clearBeforeHandle) {
            $this->resetMemory();
        }
    }

    private function resetMemory(): void
    {
        $this->resetter->reset();

        \gc_collect_cycles();
    }
}
