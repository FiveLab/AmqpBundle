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

namespace FiveLab\Bundle\AmqpBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

readonly class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('fivelab_amqp');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->append($this->getListenersDefinition())
                ->append($this->getDelayDefinition())
                ->append($this->getRoundRobinDefinition())
                ->append($this->getConnectionsNodeDefinition())
                ->append($this->getChannelsNodeDefinition())
                ->append($this->getExchangesNodeDefinition())
                ->append($this->getQueuesNodeDefinition())
                ->append($this->getQueueArgumentsNodeDefinition('queue_default_arguments'))
                ->append($this->getConsumersNodeDefinition())
                ->append($this->getConsumerDefaults())
                ->append($this->getPublishersNodeDefinition())
                ->append($this->getMiddlewareNodeDefinition('publisher_'))
            ->end();

        return $treeBuilder;
    }

    private function getListenersDefinition(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('listeners');

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('release_memory')
                    ->defaultValue(null)
                    ->info('Enable release memory listener (true - clear before handle, false - after handle, null - disable listener).')
                    ->validate()
                        ->ifFalse(static fn (mixed $value) => null === $value || \is_bool($value))
                        ->thenInvalid('Invalid value for "release_memory". Must be bool or null.')
                    ->end()
                ->end()

                ->scalarNode('ping_dbal_connections')
                    ->defaultValue(null)
                    ->info('Enable ping DBAL connections listeners (number - seconds for ping interval, null - disable listener)')
                    ->validate()
                        ->ifFalse(static fn (mixed $value) => null === $value || \is_int($value) || $value < 1)
                        ->thenInvalid('Invalid value for "ping_dbal_connections". Must be integer or null.')
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private function getDelayDefinition(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('delay');

        $node
            ->children()
                ->scalarNode('exchange')
                    ->defaultValue('delay')
                    ->info('The exchange name for use delay system.')
                ->end()

                ->scalarNode('expired_queue')
                    ->defaultValue('delay.message_expired')
                    ->info('The name of queue for expired messages.')
                ->end()

                ->scalarNode('consumer_key')
                    ->defaultValue('delay_expired')
                    ->info('The key of consumer for handle expired messages.')
                ->end()

                ->scalarNode('connection')
                    ->isRequired()
                    ->info('The name of connection.')
                ->end()

                ->append($this->getStrategyNodeDefinition())

                ->arrayNode('delays')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('', false)
                    ->beforeNormalization()
                        ->always(static function (array $delays) {
                            foreach ($delays as $key => $delayInfo) {
                                if (!\array_key_exists('queue', $delayInfo)) {
                                    $delays[$key]['queue'] = \sprintf('delay.landfill.%s', $key);
                                }

                                if (!\array_key_exists('routing', $delayInfo)) {
                                    $delays[$key]['routing'] = \sprintf('delay.%s', $key);
                                }
                            }

                            return $delays;
                        })
                    ->end()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('queue')
                                ->info('The name of queue for landfill.')
                            ->end()

                            ->scalarNode('routing')
                                ->info('The routing key for publish message.')
                            ->end()

                            ->integerNode('ttl')
                                ->info('The TTL in milliseconds.')
                                ->isRequired()
                            ->end()

                            ->arrayNode('publishers')
                                ->info('The publishers configuration.')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('channel')
                                            ->info('The channel key for create publisher.')
                                            ->defaultValue(null)
                                        ->end()

                                        ->booleanNode('savepoint')
                                            ->info('Use savepoint functionality?')
                                            ->defaultValue(false)
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private function getRoundRobinDefinition(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('round_robin');

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')
                    ->defaultValue('%kernel.debug%')
                    ->info('Enable round robin?')
                ->end()

                ->integerNode('executes_messages_per_consumer')
                    ->defaultValue(100)
                    ->info('Count of executes messages per one consumer.')
                ->end()

                ->floatNode('consumers_read_timeout')
                    ->defaultValue(10.0)
                    ->info('The read timeout for one consumer.')
                ->end()
            ->end();

        return $node;
    }

    private function getQueuesNodeDefinition(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('queues');

        $node
            ->defaultValue([])
            ->useAttributeAsKey('', false)
            ->beforeNormalization()
                ->always(static function ($queues) {
                    if (!\is_array($queues)) {
                        return $queues;
                    }

                    foreach ($queues as $key => $queueInfo) {
                        if (!\array_key_exists('name', $queueInfo)) {
                            $queues[$key]['name'] = $key;
                        }
                    }

                    return $queues;
                })
            ->end();

        /** @var ArrayNodeDefinition $prototypeNode */
        $prototypeNode = $node->prototype('array');

        $prototypeNode
            ->children()
                ->scalarNode('connection')
                    ->isRequired()
                    ->info('The connection for consume queue.')
                ->end()

                ->scalarNode('name')
                    ->isRequired()
                    ->info('The name of queue for consumer.')
                ->end()

                ->booleanNode('durable')
                    ->defaultTrue()
                    ->info('Is durable')
                ->end()

                ->scalarNode('passive')
                    ->defaultFalse()
                    ->validate()
                        ->ifTrue(self::isBoolOrExpressionClosure())
                        ->thenInvalid('The param "passive" must be a boolean or string with expression language (start with "@=")')
                    ->end()
                    ->info('Is passive?')
                ->end()

                ->booleanNode('exclusive')
                    ->defaultFalse()
                    ->info('Is exclusive?')
                ->end()

                ->booleanNode('auto_delete')
                    ->defaultFalse()
                    ->info('Auto delete queue?')
                ->end()

                ->append($this->getBindingsNodeDefinition('bindings'))
                ->append($this->getBindingsNodeDefinition('unbindings'))
                ->append($this->getQueueArgumentsNodeDefinition('arguments'))
            ->end();

        return $node;
    }

    private function getQueueArgumentsNodeDefinition(string $name = 'arguments'): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition($name);

        $node
            ->normalizeKeys(false)
            ->children()
                ->scalarNode('dead-letter-exchange')
                    ->info('Add "x-dead-letter-exchange" argument')
                ->end()

                ->scalarNode('dead-letter-routing-key')
                    ->info('Add "x-dead-letter-routing-key" argument.')
                ->end()

                ->integerNode('expires')
                    ->info('Add "x-expires" argument.')
                ->end()

                ->integerNode('max-length')
                    ->info('Add "x-max-length" argument.')
                ->end()

                ->integerNode('max-length-bytes')
                    ->info('Add "x-max-length-bytes"')
                ->end()

                ->integerNode('max-priority')
                    ->info('Add "x-max-priority" argument.')
                ->end()

                ->integerNode('message-ttl')
                    ->info('Add "x-message-ttl" argument.')
                ->end()

                ->scalarNode('overflow')
                    ->info('Add "x-overflow" argument')
                    ->validate()
                        ->ifNotInArray(['drop-head', 'reject-publish', 'reject-publish-dlx'])
                        ->thenInvalid('The overflow mode %s is not valid. Available modes: "drop-head", "reject-publish" and "reject-publish-dlx".')
                    ->end()
                ->end()

                ->scalarNode('queue-master-locator')
                    ->info('Add "x-queue-master-locator" argument.')
                    ->validate()
                        ->ifNotInArray(['min-masters', 'client-local', 'random'])
                        ->thenInvalid('The queue master locator %s is not valid. Available locators: "min-masters", "client-local" and "random".')
                    ->end()
                ->end()

                ->scalarNode('queue-mode')
                    ->info('Add "x-queue-mode" argument.')
                    ->validate()
                        ->ifNotInArray(['default', 'lazy'])
                        ->thenInvalid('The queue mode %s is not valid. Available modes: "default" and "lazy".')
                    ->end()
                ->end()

                ->scalarNode('queue-type')
                    ->info('Add "x-queue-type" argument.')
                    ->validate()
                        ->ifNotInArray(['classic', 'quorum'])
                        ->thenInvalid('The queue type %s is not valid. Available types: "classic" and "quorum".')
                    ->end()
                ->end()

                ->booleanNode('single-active-consumer')
                    ->info('Add "x-single-active-consumer" argument.')
                ->end()

                ->arrayNode('custom')
                    ->example(['x-my-custom-argument' => 'some'])
                    ->normalizeKeys(false)
                    ->prototype('scalar')
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private function getPublishersNodeDefinition(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('publishers');

        $node
            ->defaultValue([])
            ->useAttributeAsKey('', false);

        /** @var ArrayNodeDefinition $prototypeNode */
        $prototypeNode = $node->prototype('array');

        $prototypeNode
            ->children()
                ->scalarNode('exchange')
                    ->isRequired()
                    ->info('The key of exchange.')
                ->end()

                ->scalarNode('channel')
                    ->defaultValue('')
                    ->info('The channel for publish messages.')
                ->end()

                ->booleanNode('savepoint')
                    ->defaultFalse()
                    ->info('Use savepoint decorator?')
                ->end()

                ->append($this->getMiddlewareNodeDefinition())
            ->end();

        return $node;
    }

    private function getConsumersNodeDefinition(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('consumers');

        $node
            ->defaultValue([])
            ->useAttributeAsKey('', false);

        /** @var ArrayNodeDefinition $prototypeNode */
        $prototypeNode = $node->prototype('array');

        $prototypeNode
            ->children()
                ->scalarNode('queue')
                    ->isRequired()
                    ->info('The key of queue.')
                ->end()

                ->scalarNode('channel')
                    ->defaultValue('')
                    ->info('The channel for consume on queue.')
                ->end()

                ->scalarNode('mode')
                    ->defaultValue('single')
                    ->info('The mode of consumer.')
                    ->validate()
                        ->ifNotInArray(['single', 'spool', 'loop'])
                        ->thenInvalid('The mode %s is not valid. Available modes: "single", "spool" and "loop".')
                    ->end()
                ->end()

                ->append($this->getStrategyNodeDefinition())

                ->scalarNode('tick_handler')
                    ->defaultNull()
                    ->info('The service id of tick handler (support only for "loop" strategy).')
                ->end()

                ->scalarNode('tag_generator')
                    ->defaultNull()
                    ->info('The service id of tag name generator for consumer.')
                ->end()

                ->scalarNode('checker')
                    ->defaultNull()
                    ->info('The service id of service for check requirements before run consumer.')
                ->end()

                ->arrayNode('message_handlers')
                    ->isRequired()
                    ->info('The list of service ids of message handlers.')
                    ->prototype('scalar')
                    ->end()
                    ->beforeNormalization()
                        ->ifTrue(static function ($value) {
                            return !\is_array($value);
                        })
                        ->then(static function ($value) {
                            return [$value];
                        })
                    ->end()
                ->end()

                ->arrayNode('options')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('requeue_on_error')
                            ->info('Requeue message on error?')
                            ->defaultValue(true)
                        ->end()

                        ->floatNode('read_timeout')
                            ->info('The read timeout (use for loop/spool consumers).')
                            ->defaultValue(300)
                        ->end()

                        ->floatNode('timeout')
                            ->info('The timeout for flush messages (use for spool consumers).')
                            ->defaultValue(30)
                        ->end()

                        ->integerNode('prefetch_count')
                            ->info('The prefetch count or the count messages for flush (use for spool consumers).')
                            ->defaultValue(3)
                        ->end()

                        ->integerNode('idle_timeout')
                            ->info('The idle timeout (microseconds) for loop strategy')
                            ->defaultValue(100000)
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private function getConsumerDefaults(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('consumer_defaults');

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->append($this->getStrategyNodeDefinition('consume'))

                ->scalarNode('tick_handler')
                    ->defaultNull()
                    ->info('The service id of tick handler (used for all consumers when not configured).')
                ->end()
            ->end();

        return $node;
    }

    private function getExchangesNodeDefinition(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('exchanges');

        $node
            ->defaultValue([])
            ->useAttributeAsKey('', false)
            ->beforeNormalization()
                ->always(static function ($exchanges) {
                    if (!\is_array($exchanges)) {
                        return $exchanges;
                    }

                    foreach ($exchanges as $key => $exchangeInfo) {
                        if (!\array_key_exists('name', $exchangeInfo)) {
                            $exchanges[$key]['name'] = $key;
                        }
                    }

                    return $exchanges;
                })
            ->end();

        /** @var ArrayNodeDefinition $prototypeNode */
        $prototypeNode = $node->prototype('array');

        $prototypeNode
            ->beforeNormalization()
                ->always(static function ($exchangeInfo) {
                    if ($exchangeInfo['name'] && (!\array_key_exists('type', $exchangeInfo) || !$exchangeInfo['type'])) {
                        $exchangeInfo['type'] = 'direct';
                    }

                    return $exchangeInfo;
                })
            ->end()
            ->children()
                ->scalarNode('name')
                    ->info('The name of exchange (For use default exchange, please fill "amq.default").')
                    ->isRequired()
                ->end()

                ->scalarNode('connection')
                    ->info('The name of connection.')
                    ->isRequired()
                ->end()

                ->scalarNode('type')
                    ->info('The type of exchange.')
                    ->validate()
                        ->ifNotInArray([
                            'direct',
                            'topic',
                            'fanout',
                            'headers',
                        ])
                        ->thenInvalid('Invalid exchange type "%s".')
                    ->end()
                ->end()

                ->booleanNode('durable')
                    ->defaultTrue()
                    ->info('Is durable?')
                ->end()

                ->scalarNode('passive')
                    ->defaultFalse()
                    ->validate()
                        ->ifTrue(self::isBoolOrExpressionClosure())
                        ->thenInvalid('The param "passive" must be a boolean or string with expression language (start with "@=")')
                    ->end()
                    ->info('Is passive?')
                ->end()

                ->append($this->getBindingsNodeDefinition('bindings'))
                ->append($this->getBindingsNodeDefinition('unbindings'))

                ->arrayNode('arguments')
                    ->normalizeKeys(false)
                    ->children()
                        ->scalarNode('alternate-exchange')
                            ->defaultNull()
                            ->info('Add "alternate-exchange" argument?')
                            ->example('norouted')
                        ->end()

                        ->arrayNode('custom')
                            ->defaultValue([])
                            ->example(['x-my-argument' => 'foo'])
                            ->normalizeKeys(false)
                            ->prototype('scalar')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private function getChannelsNodeDefinition(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('channels');

        $node
            ->useAttributeAsKey('', false)
            ->defaultValue([]);

        /** @var ArrayNodeDefinition $prototype */
        $prototype = $node->prototype('array');

        $prototype
            ->children()
                ->scalarNode('connection')
                    ->isRequired()
                    ->info('The connection for this channel.')
                ->end()
            ->end();

        return $node;
    }

    private function getConnectionsNodeDefinition(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('connections');

        $node
            ->useAttributeAsKey('', false)
            ->requiresAtLeastOneElement();

        $node
            ->beforeNormalization()
                ->ifTrue(static function ($value) {
                    return \is_array($value) && \array_key_exists('dsn', $value);
                })
                ->then(static function ($value) {
                    return ['default' => $value];
                })
            ->end();

        /** @var ArrayNodeDefinition $prototypeNode */
        $prototypeNode = $node->prototype('array');

        $prototypeNode
            ->children()
                ->scalarNode('dsn')
                    ->isRequired()
                    ->info('The DSN for connect to RabbitMQ.')
                    ->example('amqp://guest:guest@host1.com,host2.com:5672/%2f?read_timeout=60&heartbeat=30')
                ->end()
            ->end();

        return $node;
    }

    private function getBindingsNodeDefinition(string $nodeName): NodeDefinition
    {
        $node = new ArrayNodeDefinition($nodeName);

        $node
            ->requiresAtLeastOneElement();

        /** @var ArrayNodeDefinition $prototypeNode */
        $prototypeNode = $node->prototype('array');

        $prototypeNode
            ->children()
                ->scalarNode('exchange')
                    ->isRequired()
                    ->info('The exchange for binding.')
                    ->beforeNormalization()
                        ->ifTrue(static fn ($value) => $value instanceof \BackedEnum)
                        ->then(static fn (\BackedEnum $value) => $value->value)
                    ->end()
                ->end()

                ->scalarNode('routing')
                    ->isRequired()
                    ->info('The routing key for binding.')
                    ->beforeNormalization()
                        ->ifTrue(static fn($value) => $value instanceof \BackedEnum)
                        ->then(static fn(\BackedEnum $value) => $value->value)
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private function getMiddlewareNodeDefinition(string $nodePrefix = ''): NodeDefinition
    {
        $node = new ArrayNodeDefinition($nodePrefix.'middleware');

        $node
            ->defaultValue([])
            ->prototype('scalar')
            ->end();

        return $node;
    }

    private function getStrategyNodeDefinition(?string $defaultValue = null): NodeDefinition
    {
        return (new ScalarNodeDefinition('strategy'))
            ->defaultValue($defaultValue)
            ->info('The strategy for consume.')
            ->validate()
                ->ifNotInArray(['consume', 'loop'])
                ->thenInvalid('The strategy %s is not valid. Available strategies: "consume" and "loop".')
            ->end();
    }

    private static function isBoolOrExpressionClosure(): \Closure
    {
        return static function ($value) {
            if (\is_bool($value)) {
                return false;
            }

            return \strpos($value, '@=') !== 0;
        };
    }
}
