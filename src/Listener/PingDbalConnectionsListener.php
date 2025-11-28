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
use FiveLab\Component\Amqp\AmqpEvents;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class PingDbalConnectionsListener implements EventSubscriberInterface
{
    private \ArrayObject $options;

    public function __construct(private ConnectionRegistry $registry, private int $interval)
    {
        $this->options = new \ArrayObject([
            'last_ping' => \time(),
            'output'    => null,
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND    => ['onConsoleCommand', 0],
            AmqpEvents::CONSUMER_TICK => ['onConsumerTick', 0],
        ];
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->options->offsetSet('output', $event->getOutput());
    }

    public function onConsumerTick(): void
    {
        $nextPing = $this->options['last_ping'] + $this->interval;

        if ($nextPing > \time()) {
            return;
        }

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
