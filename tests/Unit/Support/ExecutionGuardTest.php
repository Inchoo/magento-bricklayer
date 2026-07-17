<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Support;

use Inchoo\MagentoBricklayer\Exception\ExecutionTimedOutException;
use Inchoo\MagentoBricklayer\Support\ExecutionGuard;
use PHPUnit\Framework\TestCase;

class ExecutionGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        ExecutionGuard::stopTimeout();
        ExecutionGuard::endRequest();
    }

    public function testStartTimeoutThrowsCatchableExceptionWhenExceeded(): void
    {
        if (!ExecutionGuard::supportsAlarm()) {
            self::markTestSkipped('pcntl not available');
        }

        $this->expectException(ExecutionTimedOutException::class);

        ExecutionGuard::startTimeout(1);
        // Busy-loop well past the 1s alarm; the thrown exception exits the loop.
        // The 5s bound only prevents an infinite loop if the alarm never fires.
        $end = microtime(true) + 5;
        $noise = 'busy';
        while (microtime(true) < $end) {
            $noise = hash('sha256', $noise);
        }
    }

    public function testStopTimeoutDisarmsPendingAlarm(): void
    {
        if (!ExecutionGuard::supportsAlarm()) {
            self::markTestSkipped('pcntl not available');
        }

        ExecutionGuard::startTimeout(1);
        ExecutionGuard::stopTimeout();

        // If the alarm were still armed it would fire (as SIG_DFL: process death)
        // during this sleep; surviving it proves the disarm worked.
        usleep(1_300_000);
        $this->assertTrue(true);
    }

    public function testShutdownHookEmitsJsonRpcToolErrorOnFatal(): void
    {
        $fixture = dirname(__DIR__, 2) . '/Fixtures/execution_guard_fatal.php';
        $autoload = $this->composerAutoloadPath();

        exec(
            sprintf(
                '%s %s %s 2>/dev/null',
                escapeshellarg(\PHP_BINARY),
                escapeshellarg($fixture),
                escapeshellarg($autoload)
            ),
            $outputLines,
            $exitCode
        );
        $stdout = implode("\n", $outputLines);

        $this->assertNotSame(0, $exitCode, 'Fixture must die on the fatal');
        $this->assertStringContainsString('"jsonrpc":"2.0"', $stdout);
        $this->assertStringContainsString('"id":42', $stdout);
        $this->assertStringContainsString('"isError":true', $stdout);
        $this->assertStringContainsString('Cannot redeclare', $stdout);

        // The response must be one parseable JSON line (StdioTransport framing).
        $jsonLine = null;
        foreach ($outputLines as $line) {
            if (str_contains($line, '"jsonrpc"')) {
                $jsonLine = $line;
                break;
            }
        }
        $this->assertNotNull($jsonLine);
        $decoded = json_decode($jsonLine, true);
        $this->assertIsArray($decoded);
        $this->assertSame(42, $decoded['id']);
        $this->assertTrue($decoded['result']['isError']);
    }

    public function testShutdownHookStaysSilentWithoutInFlightRequest(): void
    {
        // No beginRequest: onShutdown must be a no-op even after a recorded error.
        ob_start();
        ExecutionGuard::onShutdown();
        $this->assertSame('', ob_get_clean());
    }

    private function composerAutoloadPath(): string
    {
        $classLoaderFile = (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName();
        $this->assertIsString($classLoaderFile);

        return dirname($classLoaderFile) . '/../autoload.php';
    }
}
