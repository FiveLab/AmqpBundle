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

use FiveLab\Bundle\AmqpBundle\DependencyInjection\AmqpExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;

abstract class AmqpExtensionTestCase extends AbstractExtensionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->setParameter('kernel.debug', false);
    }

    protected function getContainerExtensions(): array
    {
        return [new AmqpExtension()];
    }

    protected function assertParameter(string $parameter, mixed $value): void
    {
        self::assertTrue($this->container->hasParameter($parameter), \sprintf('Missed parameter "%s" in container.', $parameter));

        self::assertEquals($value, $this->container->getParameter($parameter), \sprintf('Invalid parameter "%s" in container.', $parameter));
    }

    protected function assertService(string $id, ?string $class = null, ?array $arguments = null, ?array $factory = null, ?array $calls = null): void
    {
        self::assertTrue($this->container->hasDefinition($id), \sprintf('Missed definition "%s" in container.', $id));

        $def = $this->container->getDefinition($id);

        if (null !== $class) {
            if (\str_starts_with($class, '@')) {
                // Check by parent
                $parentDef = \substr($class, 1);

                self::assertTrue($this->container->hasDefinition($parentDef), \sprintf(
                    'Missed parent definition "%s" for definition "%s".',
                    $parentDef,
                    $id
                ));
            } else {
                self::assertEquals($class, $def->getClass(), \sprintf('Invalid class for definition "%s".', $id));
            }
        }

        if (null !== $arguments) {
            self::assertEquals($arguments, \array_values($def->getArguments()), \sprintf('Invalid arguments for definition "%s".', $id));
        }

        if (null !== $factory) {
            self::assertEquals($factory, $def->getFactory(), \sprintf('Invalid factory for definition "%s".', $id));
        }

        if (null !== $calls) {
            $defCalls = [$def->getMethodCalls()];

            if ($def instanceof ChildDefinition) {
                $defCalls[] = $this->container->getDefinition($def->getParent())->getMethodCalls();
            }

            $defCalls = \array_merge(...$defCalls);

            $actualCalls = [];

            foreach ($defCalls as [$callMethod, $callArguments]) {
                if (!\array_key_exists($callMethod, $actualCalls)) {
                    $actualCalls[$callMethod] = [];
                }

                $actualCalls[$callMethod][] = $callArguments;
            }

            self::assertEquals($calls, $actualCalls);
        }
    }
}
