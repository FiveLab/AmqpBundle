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

namespace FiveLab\Bundle\AmqpBundle\Registries;

use FiveLab\Component\Amqp\Consumer\ConsumerInterface;
use FiveLab\Component\Amqp\Consumer\Registry\ConsumerRegistryInterface;
use FiveLab\Component\Amqp\Exception\ConsumerNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple consumer registry
 */
class ContainerConsumerRegistry implements ConsumerRegistryInterface
{
    /**
     * @var array|string[]
     */
    private array $consumerIds = [];

    /**
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Add consumer's service identifier to registry
     *
     * @param string $key
     * @param string $consumerServiceId
     */
    public function add(string $key, string $consumerServiceId): void
    {
        $this->consumerIds[$key] = $consumerServiceId;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): ConsumerInterface
    {
        if (\array_key_exists($key, $this->consumerIds)) {
            /** @var ConsumerInterface $consumer */
            $consumer = $this->container->get($this->consumerIds[$key]);

            return $consumer;
        }

        throw new ConsumerNotFoundException(\sprintf(
            'The consumer with key "%s" was not found.',
            $key
        ));
    }
}
