<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresValidVerbosity;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RespondsWithErrors;
use Mcp\Capability\Attribute\McpTool;

/**
 * Runtime introspection of Magento's merged message-queue wiring — the consumer →
 * topic → queue → exchange topology assembled across communication.xml,
 * queue_consumer.xml, queue_topology.xml and queue_publisher.xml of every module.
 */
class MessageQueueTools
{
    use RequiresMagento;
    use RequiresValidVerbosity;
    use RespondsWithErrors;

    /**
     * Inspect the runtime-merged message-queue configuration.
     *
     * @param string $consumer  Filter consumers by name substring (optional).
     * @param string $topic     Filter topics/bindings/publishers by name substring (optional).
     * @param string $verbosity minimal | standard | detailed
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'message-queue-inspect',
        description: 'Inspect Magento\'s runtime-merged message-queue wiring: consumers, topics, '
            . 'queue/exchange bindings, publishers and handlers merged across all modules. '
            . 'Each section degrades independently if a sub-config is absent. Optional consumer and '
            . 'topic name filters. Params: consumer, topic, verbosity (minimal|standard|detailed).',
        meta: ['hidden' => true]
    )]
    public function inspectMessageQueue(
        string $consumer = '',
        string $topic = '',
        string $verbosity = 'standard'
    ): array {
        if ($error = $this->requireValidVerbosity($verbosity)) {
            return $error;
        }

        if ($error = $this->requireMagento()) {
            return $error;
        }

        return $this->runGuarded(function () use ($consumer, $topic, $verbosity) {
            $unavailable = [];

            $consumers = $this->section($unavailable, 'consumers', fn() => $this->collectConsumers($consumer));
            $allBinds = $this->section($unavailable, 'binds', fn() => $this->collectBinds(''));

            // Cross-link each consumer to the topics/exchange feeding its queue (uses all binds).
            $consumers = $this->linkConsumers($consumers, $allBinds);

            // A consumer filter scopes the whole response: restrict the topic-keyed sections to the
            // topics wired to the matched consumers (via their queue bindings), so a focused query
            // returns just those consumers' wiring rather than the entire topic/publisher catalogue.
            $consumerQueues = $consumer !== '' ? array_column($consumers, 'queue') : null;

            $binds = array_values(array_filter($allBinds, function (array $bind) use ($topic, $consumerQueues) {
                if ($topic !== '' && stripos((string) $bind['topic'], $topic) === false) {
                    return false;
                }
                if ($consumerQueues !== null && !in_array($bind['queue'], $consumerQueues, true)) {
                    return false;
                }
                return true;
            }));

            $scope = $consumerQueues !== null
                ? array_values(array_unique(array_filter(array_column($binds, 'topic'))))
                : null;

            $topics = $this->section($unavailable, 'topics', fn() => $this->collectTopics($topic, $scope));
            $publishers = $this->section($unavailable, 'publishers', fn() => $this->collectPublishers($topic, $scope));
            $exchanges = $this->section($unavailable, 'exchanges', fn() => $this->collectExchanges($topic, $scope));

            $result = [
                'filters' => [
                    'consumer' => $consumer ?: 'all',
                    'topic' => $topic ?: 'all',
                ],
                'summary' => [
                    'consumers' => count($consumers),
                    'topics' => count($topics),
                    'binds' => count($binds),
                    'publishers' => count($publishers),
                    'exchanges' => count($exchanges),
                ],
            ];

            if ($unavailable !== []) {
                $result['unavailable_sections'] = $unavailable;
            }

            $result += $this->shape($verbosity, $consumers, $topics, $binds, $publishers, $exchanges);

            $result['_skill_hint'] = 'For consumer/publisher patterns: development-context category=message-queue';

            return $result;
        });
    }

    /**
     * Run one section collector, recording the section name if its sub-config is unavailable.
     *
     * @param list<string> $unavailable
     * @param callable(): list<array<string, mixed>> $collector
     * @return list<array<string, mixed>>
     */
    private function section(array &$unavailable, string $name, callable $collector): array
    {
        try {
            return $collector();
        } catch (\Throwable) {
            $unavailable[] = $name;
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectConsumers(string $filter): array
    {
        $config = MagentoBootstrap::get(\Magento\Framework\MessageQueue\Consumer\ConfigInterface::class);

        $consumers = [];
        foreach ($config->getConsumers() as $consumer) {
            $name = $consumer->getName();
            if ($filter !== '' && stripos($name, $filter) === false) {
                continue;
            }

            $handlers = [];
            foreach ($consumer->getHandlers() as $handler) {
                $handlers[] = ['type' => $handler->getType(), 'method' => $handler->getMethod()];
            }

            $consumers[] = [
                'name' => $name,
                'queue' => $consumer->getQueue(),
                'connection' => $consumer->getConnection(),
                'consumer_instance' => $consumer->getConsumerInstance(),
                'max_messages' => $consumer->getMaxMessages(),
                'handlers' => $handlers,
            ];
        }

        usort($consumers, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $consumers;
    }

    /**
     * Topic → exchange → queue bindings (the wiring backbone).
     *
     * @return list<array<string, mixed>>
     */
    private function collectBinds(string $filter): array
    {
        $config = MagentoBootstrap::get(\Magento\Framework\MessageQueue\ConfigInterface::class);

        $binds = [];
        foreach ($config->getBinds() as $bind) {
            $topic = (string) ($bind['topic'] ?? '');
            if ($filter !== '' && stripos($topic, $filter) === false) {
                continue;
            }

            $binds[] = [
                'topic' => $topic,
                'exchange' => $bind['exchange'] ?? null,
                'queue' => $bind['queue'] ?? null,
            ];
        }

        return $binds;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectTopics(string $filter, ?array $scope = null): array
    {
        $config = MagentoBootstrap::get(\Magento\Framework\Communication\ConfigInterface::class);

        $topics = [];
        foreach ($config->getTopics() as $name => $data) {
            if ($filter !== '' && stripos((string) $name, $filter) === false) {
                continue;
            }
            if ($scope !== null && !in_array((string) $name, $scope, true)) {
                continue;
            }

            $handlers = [];
            foreach (($data['handlers'] ?? []) as $handlerName => $handler) {
                $handlers[] = [
                    'name' => (string) $handlerName,
                    'type' => $handler['type'] ?? null,
                    'method' => $handler['method'] ?? null,
                ];
            }

            $topics[] = [
                'name' => (string) $name,
                'synchronous' => (bool) ($data['is_synchronous'] ?? false),
                'request_type' => $data['request_type'] ?? null,
                'handlers' => $handlers,
                'request' => $data['request'] ?? null,
            ];
        }

        usort($topics, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $topics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectPublishers(string $filter, ?array $scope = null): array
    {
        $config = MagentoBootstrap::get(\Magento\Framework\MessageQueue\Publisher\ConfigInterface::class);

        $publishers = [];
        foreach ($config->getPublishers() as $publisher) {
            $topic = $publisher->getTopic();
            if ($filter !== '' && stripos((string) $topic, $filter) === false) {
                continue;
            }
            if ($scope !== null && !in_array((string) $topic, $scope, true)) {
                continue;
            }

            $connection = $publisher->getConnection();
            $publishers[] = [
                'topic' => $topic,
                'connection' => $connection->getName(),
                'exchange' => $connection->getExchange(),
                'disabled' => $publisher->isDisabled(),
            ];
        }

        usort($publishers, fn($a, $b) => strcmp((string) $a['topic'], (string) $b['topic']));

        return $publishers;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectExchanges(string $filter, ?array $scope = null): array
    {
        $config = MagentoBootstrap::get(\Magento\Framework\MessageQueue\Topology\ConfigInterface::class);

        $exchanges = [];
        foreach ($config->getExchanges() as $exchange) {
            $bindings = [];
            foreach ($exchange->getBindings() as $binding) {
                $topic = $binding->getTopic();
                if ($filter !== '' && stripos((string) $topic, $filter) === false) {
                    continue;
                }
                if ($scope !== null && !in_array((string) $topic, $scope, true)) {
                    continue;
                }
                $bindings[] = [
                    'id' => $binding->getId(),
                    'topic' => $topic,
                    'destination_type' => $binding->getDestinationType(),
                    'destination' => $binding->getDestination(),
                ];
            }

            // When a topic/consumer filter is set, drop exchanges that contributed no matching binding.
            if (($filter !== '' || $scope !== null) && $bindings === []) {
                continue;
            }

            $exchanges[] = [
                'name' => $exchange->getName(),
                'connection' => $exchange->getConnection(),
                'type' => $exchange->getType(),
                'bindings' => $bindings,
            ];
        }

        usort($exchanges, fn($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

        return $exchanges;
    }

    /**
     * Attach the topics and exchange that feed each consumer's queue (matched via binds).
     *
     * @param list<array<string, mixed>> $consumers
     * @param list<array<string, mixed>> $binds
     * @return list<array<string, mixed>>
     */
    private function linkConsumers(array $consumers, array $binds): array
    {
        foreach ($consumers as &$consumer) {
            $topics = [];
            $exchanges = [];
            foreach ($binds as $bind) {
                if (($bind['queue'] ?? null) === $consumer['queue']) {
                    if (!empty($bind['topic'])) {
                        $topics[] = $bind['topic'];
                    }
                    if (!empty($bind['exchange'])) {
                        $exchanges[] = $bind['exchange'];
                    }
                }
            }
            $consumer['topics'] = array_values(array_unique($topics));
            $consumer['exchanges'] = array_values(array_unique($exchanges));
        }

        return $consumers;
    }

    /**
     * Shape the collected sections according to verbosity.
     *
     * @param list<array<string, mixed>> $consumers
     * @param list<array<string, mixed>> $topics
     * @param list<array<string, mixed>> $binds
     * @param list<array<string, mixed>> $publishers
     * @param list<array<string, mixed>> $exchanges
     * @return array<string, mixed>
     */
    private function shape(
        string $verbosity,
        array $consumers,
        array $topics,
        array $binds,
        array $publishers,
        array $exchanges
    ): array {
        if ($verbosity === 'minimal') {
            return [
                'consumers' => array_column($consumers, 'name'),
                'topics' => array_column($topics, 'name'),
            ];
        }

        if ($verbosity === 'detailed') {
            return [
                'consumers' => $consumers,
                'topics' => $topics,
                'binds' => $binds,
                'publishers' => $publishers,
                'exchanges' => $exchanges,
            ];
        }

        // standard: full consumers + binds + publishers, condensed topics/exchanges.
        return [
            'consumers' => array_map(static function (array $c): array {
                unset($c['request']);
                return $c;
            }, $consumers),
            'topics' => array_map(static fn(array $t): array => [
                'name' => $t['name'],
                'synchronous' => $t['synchronous'],
                'handler_count' => count($t['handlers'] ?? []),
            ], $topics),
            'binds' => $binds,
            'publishers' => $publishers,
            'exchanges' => array_map(static fn(array $e): array => [
                'name' => $e['name'],
                'connection' => $e['connection'],
                'type' => $e['type'],
                'binding_count' => count($e['bindings'] ?? []),
            ], $exchanges),
        ];
    }
}
