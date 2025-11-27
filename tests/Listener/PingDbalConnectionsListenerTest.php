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

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use FiveLab\Bundle\AmqpBundle\Listener\PingDbalConnectionsListener;
use FiveLab\Component\Amqp\AmqpEvents;
use FiveLab\Component\Amqp\Event\ConsumerTickEvent;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class PingDbalConnectionsListenerTest extends TestCase
{
    #[Test]
    public function shouldSuccessGetListeners(): void
    {
        self::assertEquals([
            ConsoleEvents::SIGNAL     => ['onConsoleSignal', 0],
            AmqpEvents::CONSUMER_TICK => ['onConsumerTick', 0],
        ], PingDbalConnectionsListener::getSubscribedEvents());
    }

    #[Test]
    #[RequiresPhpExtension('pcntl')]
    #[TestWith([OutputInterface::VERBOSITY_DEBUG, true])]
    #[TestWith([OutputInterface::VERBOSITY_VERBOSE, false])]
    public function shouldSuccessPing(int $verbose, bool $expectedOutput): void
    {
        $registry = $this->makeRegistry([
            'conn1' => $this->makeConnection(true, true),
            'conn2' => $this->makeConnection(false, false),
            'conn3' => $this->makeConnection(true, true),
        ]);

        $consoleEvent = $this->makeConsoleSignalEvent($output = new BufferedOutput($verbose));

        $listener = new PingDbalConnectionsListener($registry, 60);
        $this->changeLastPingTime($listener, \time() - 61);
        $listener->onConsoleSignal($consoleEvent);
        $listener->onConsumerTick();

        $expectedOutputBuffer = '';

        if ($expectedOutput) {
            $expectedOutputBuffer = <<<OUTPUT
Ping conn1 database connection.
Ping conn3 database connection.

OUTPUT;
        }

        self::assertEquals($expectedOutputBuffer, $output->fetch());
        self::assertEqualsWithDelta(\time(), $this->getLastPingTime($listener), 2);
    }

    #[Test]
    #[RequiresPhpExtension('pcntl')]
    public function shouldNotPingForSmallInterval(): void
    {
        $registry = $this->makeRegistry([
            'conn1' => $this->makeConnection(null, false),
            'conn2' => $this->makeConnection(null, false),
        ]);

        $consoleEvent = $this->makeConsoleSignalEvent(new BufferedOutput());

        $listener = new PingDbalConnectionsListener($registry, 60);
        $this->changeLastPingTime($listener, \time() - 30);
        $listener->onConsoleSignal($consoleEvent);
        $listener->onConsumerTick();
    }

    #[Test]
    public function shouldIgnoreForWrongSignal(): void
    {
        $registry = $this->makeRegistry([
            'conn1' => $this->makeConnection(null, false),
            'conn2' => $this->makeConnection(null, false),
        ]);

        $consoleEvent = $this->makeConsoleSignalEvent(new BufferedOutput(), \SIGINT);

        $listener = new PingDbalConnectionsListener($registry, 60, [\SIGUSR1]);
        $this->changeLastPingTime($listener, \time() - 100);
        $listener->onConsoleSignal($consoleEvent);
        $listener->onConsumerTick();
    }

    private function changeLastPingTime(PingDbalConnectionsListener $listener, int $lastPing): void
    {
        $ref = new \ReflectionProperty($listener, 'options');
        /** @var \ArrayAccess $options */
        $options = $ref->getValue($listener);

        $options->offsetSet('last_ping', $lastPing);
    }

    private function getLastPingTime(PingDbalConnectionsListener $listener): int
    {
        $ref = new \ReflectionProperty($listener, 'options');
        /** @var \ArrayAccess $options */
        $options = $ref->getValue($listener);

        return $options->offsetGet('last_ping');
    }

    private function makeConsoleSignalEvent(BufferedOutput $output, int $signal = \SIGALRM): ConsoleSignalEvent
    {
        return new ConsoleSignalEvent($this->createMock(Command::class), $this->createMock(InputInterface::class), $output, $signal);
    }

    private function makeRegistry(array $connections): ConnectionRegistry
    {
        $registry = $this->createMock(ConnectionRegistry::class);

        $registry->expects($this->any())
            ->method('getConnections')
            ->willReturn($connections);

        return $registry;
    }

    private function makeConnection(?bool $connected, bool $expectedPing): Connection
    {
        $connection = $this->createMock(Connection::class);

        if (null !== $connected) {
            $connection->expects($this->once())
                ->method('isConnected')
                ->willReturn($connected);
        } else {
            $connection->expects($this->never())
                ->method('isConnected');
        }

        if ($expectedPing) {
            $connection->expects($this->once())
                ->method('executeQuery')
                ->with('SELECT 1');
        } else {
            $connection->expects($this->never())
                ->method('executeQuery');
        }

        return $connection;
    }
}
