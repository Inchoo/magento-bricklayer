<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Integration;

/**
 * End-to-end coverage of the layer nothing else touches: the actual MCP
 * server process. Spawns bin/bricklayer-mcp exactly as an agent would
 * (stdio, newline-delimited JSON-RPC 2.0), performs the initialize
 * handshake, lists tools, and calls one, asserting on the wire protocol
 * rather than on tool classes.
 */
final class McpServerProtocolTest extends IntegrationTestCase
{
    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    protected function tearDown(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $this->pipes = [];

        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
        $this->process = null;
    }

    public function testStdioHandshakeToolListingAndToolCall(): void
    {
        $this->startServer();

        $init = $this->request(1, 'initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'bricklayer-integration-suite', 'version' => '0.0.0'],
        ]);
        $serverInfo = self::arrayValue(self::arrayValue($init, 'result'), 'serverInfo');
        self::assertSame('magento-bricklayer', $serverInfo['name'] ?? null);

        $this->notify('notifications/initialized');

        $list = $this->request(2, 'tools/list', new \stdClass());
        $names = [];
        foreach (self::arrayValue(self::arrayValue($list, 'result'), 'tools') as $tool) {
            if (is_array($tool) && is_string($tool['name'] ?? null)) {
                $names[] = $tool['name'];
            }
        }
        self::assertGreaterThan(10, count($names), 'Tier 1 tools should be listed');
        self::assertContains('search-tools', $names);
        self::assertContains('check-class', $names);

        $call = $this->request(3, 'tools/call', [
            'name' => 'application-info',
            'arguments' => new \stdClass(),
        ]);
        $callResult = self::arrayValue($call, 'result');
        self::assertNotSame(true, $callResult['isError'] ?? null, $this->drainStderr());

        $payload = '';
        foreach (self::arrayValue($callResult, 'content') as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $payload .= $block['text'];
            }
        }
        self::assertStringContainsString('magento_version', $payload);
    }

    private function startServer(): void
    {
        $root = self::magentoRoot();
        $server = $root . '/vendor/inchoo/magento-bricklayer/bin/bricklayer-mcp';
        if (!file_exists($server)) {
            $server = dirname(__DIR__, 2) . '/bin/bricklayer-mcp';
        }

        $process = proc_open(
            [PHP_BINARY, $server],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root
        );

        if (!is_resource($process)) {
            self::fail('Failed to start bricklayer-mcp');
        }

        $this->process = $process;
        $this->pipes = $pipes;
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);
    }

    /**
     * @param array<string, mixed>|\stdClass $params
     * @return array<mixed>
     */
    private function request(int $id, string $method, array|\stdClass $params): array
    {
        $this->write(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]);

        $line = $this->readLine(180);
        $decoded = json_decode($line, true);

        if (!is_array($decoded)) {
            self::fail(sprintf('Non-JSON response to %s: %s%s', $method, $line, $this->drainStderr()));
        }

        self::assertSame($id, $decoded['id'] ?? null, 'Response id mismatch for ' . $method . $this->drainStderr());

        if (array_key_exists('error', $decoded)) {
            self::fail(sprintf(
                'JSON-RPC error for %s: %s%s',
                $method,
                substr(var_export($decoded['error'], true), 0, 1000),
                $this->drainStderr()
            ));
        }

        return $decoded;
    }

    private function notify(string $method): void
    {
        $this->write(['jsonrpc' => '2.0', 'method' => $method]);
    }

    /**
     * @param array<string, mixed> $message
     */
    private function write(array $message): void
    {
        $json = json_encode($message);
        if ($json === false) {
            self::fail('Failed to encode JSON-RPC message');
        }

        fwrite($this->pipes[0], $json . "\n");
        fflush($this->pipes[0]);
    }

    /**
     * Read one newline-terminated JSON-RPC frame. Generous timeout: the
     * first response waits behind the full Magento bootstrap in the child.
     */
    private function readLine(int $timeoutSeconds): string
    {
        $buffer = '';
        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            $chunk = fgets($this->pipes[1]);

            if (is_string($chunk)) {
                $buffer .= $chunk;
                if (str_ends_with($buffer, "\n")) {
                    return rtrim($buffer, "\n");
                }
                continue;
            }

            $read = [$this->pipes[1]];
            $write = null;
            $except = null;
            stream_select($read, $write, $except, 1);
        }

        self::fail('Timed out waiting for a response line.' . $this->drainStderr());
    }

    private function drainStderr(): string
    {
        if (!isset($this->pipes[2]) || !is_resource($this->pipes[2])) {
            return '';
        }

        $err = stream_get_contents($this->pipes[2]);

        return is_string($err) && $err !== '' ? "\nServer stderr:\n" . $err : '';
    }
}
