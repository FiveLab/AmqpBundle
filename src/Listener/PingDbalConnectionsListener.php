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

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use FiveLab\Component\Amqp\Event\ConsumerTickEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class PingDbalConnectionsListener implements EventSubscriberInterface
{
    private \ArrayObject $options;

    public function __construct(private ConnectionRegistry $registry, private int $interval, private array $signals = [\SIGALRM])
    {
        $this->options = new \ArrayObject([
            'ping'      => false,
            'last_ping' => \time(),
            'output'    => null,
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::SIGNAL    => ['onConsoleSignal', 0],
            ConsumerTickEvent::class => ['onConsumerTick', 0],
        ];
    }

    public function onConsoleSignal(ConsoleSignalEvent $event): void
    {
        if (!\in_array($event->getHandlingSignal(), $this->signals, true)) {
            return;
        }

        $nextPing = $this->options['last_ping'] + $this->interval;

        if ($nextPing < \time()) {
            $this->options->offsetSet('ping', true);
            $this->options->offsetSet('output', $event->getOutput());
        }
    }

    public function onConsumerTick(): void
    {
        if (!$this->options['ping']) {
            return;
        }

        $this->options->offsetSet('ping', false);
        $this->options->offsetSet('last_ping', \time());

        /** @var Connection $connection */
        foreach ($this->registry->getConnections() as $key => $connection) {
            if ($connection->isConnected()) {
                $connection->executeQuery('SELECT 1');

                $this->options['output']->writeln(\sprintf(
                    'Ping <comment>%s</comment> database connection.',
                    $key
                ), OutputInterface::VERBOSITY_DEBUG);
            }
        }
    }
}
