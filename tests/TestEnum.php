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

namespace FiveLab\Bundle\AmqpBundle\Tests;

enum TestEnum: string
{
    case Test = 'test';
    case Foo = 'foo';
    case Bar = 'bar';
    case Some = 'some';
}
