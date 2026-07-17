<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Support;

use Inchoo\MagentoBricklayer\Exception\ExecutionTimedOutException;

/**
 * Protects the long-lived MCP server process from user code run by code-runner.
 *
 * Two independent mechanisms:
 *
 * 1. Wall-clock timeout via SIGALRM (startTimeout/stopTimeout). set_time_limit()
 *    counts only CPU time (a stalled DB query never triggers it) and its fatal is
 *    uncatchable — it killed the whole server process. The alarm handler throws
 *    ExecutionTimedOutException instead, which the code-runner catch blocks turn
 *    into a normal error response after rolling back the transaction. Requires
 *    pcntl (CLI on Linux/macOS); callers must fall back to set_time_limit()
 *    when supportsAlarm() is false.
 *
 * 2. Fatal-error reporter via register_shutdown_function (beginRequest/endRequest).
 *    Fatals the alarm cannot catch (OOM, redeclare, …) still kill the process, but
 *    the shutdown hook emits a proper JSON-RPC tool-error response for the in-flight
 *    request before dying, so the client sees the real error instead of a dead socket.
 */
final class ExecutionGuard
{
    private static bool $shutdownRegistered = false;

    /** @var array{tool: string, id: int|string|null}|null */
    private static ?array $inFlight = null;

    public static function supportsAlarm(): bool
    {
        return \function_exists('pcntl_async_signals')
            && \function_exists('pcntl_signal')
            && \function_exists('pcntl_alarm')
            && \defined('SIGALRM');
    }

    /**
     * Arm a wall-clock timeout. The SIGALRM handler throws, so the exception
     * surfaces inside whatever user code is running when the alarm fires.
     */
    public static function startTimeout(int $seconds): void
    {
        if (!self::supportsAlarm()) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(\SIGALRM, static function () use ($seconds): void {
            throw new ExecutionTimedOutException(
                sprintf('Execution timed out after %d second(s) (wall-clock limit).', $seconds)
            );
        });
        pcntl_alarm(max(1, $seconds));
    }

    /**
     * Disarm the timeout. Must run in a finally block so a pending alarm never
     * fires outside the guarded region (it would then kill the server loop).
     */
    public static function stopTimeout(): void
    {
        if (!self::supportsAlarm()) {
            return;
        }

        pcntl_alarm(0);
        pcntl_signal(\SIGALRM, \SIG_DFL);
    }

    /**
     * Mark a tool request as in flight so the shutdown hook can answer it on a fatal.
     */
    public static function beginRequest(string $tool, int|string|null $requestId): void
    {
        self::$inFlight = ['tool' => $tool, 'id' => $requestId];

        if (!self::$shutdownRegistered) {
            register_shutdown_function([self::class, 'onShutdown']);
            self::$shutdownRegistered = true;
        }
    }

    public static function endRequest(): void
    {
        self::$inFlight = null;
    }

    /**
     * Shutdown hook: if the process is dying on a fatal while a request is in
     * flight, emit a JSON-RPC tool-error response (newline-delimited, matching
     * StdioTransport framing) so the client gets the real error instead of a
     * silent connection drop. Public only because register_shutdown_function
     * needs to call it.
     */
    public static function onShutdown(): void
    {
        if (self::$inFlight === null) {
            return;
        }
        $inFlight = self::$inFlight;
        self::$inFlight = null;

        $error = error_get_last();
        if (
            $error === null
            || !\in_array($error['type'], [\E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR], true)
        ) {
            return;
        }

        // User-code output buffers may still be open (the fatal aborted mid-capture);
        // drop them so the JSON-RPC line reaches STDOUT unwrapped.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $message = sprintf(
            '%s died on a fatal error: %s (%s:%d). '
            . 'The MCP server process is terminating and must be restarted/reconnected.',
            $inFlight['tool'],
            $error['message'],
            $error['file'],
            $error['line']
        );

        fwrite(\STDERR, '[FATAL] ' . $message . \PHP_EOL);

        $toolPayload = json_encode(
            ['error' => true, 'fatal' => true, 'message' => $message],
            \JSON_UNESCAPED_SLASHES
        );
        $response = json_encode(
            [
                'jsonrpc' => '2.0',
                'id' => $inFlight['id'],
                'result' => [
                    'content' => [['type' => 'text', 'text' => $toolPayload]],
                    'isError' => true,
                ],
            ],
            \JSON_UNESCAPED_SLASHES
        );

        if ($response !== false) {
            fwrite(\STDOUT, $response . "\n");
            fflush(\STDOUT);
        }
    }
}
