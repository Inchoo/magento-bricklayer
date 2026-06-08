<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool\Concern;

use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RespondsWithErrors;
use PHPUnit\Framework\TestCase;

class RespondsWithErrorsTest extends TestCase
{
    public function testItReturnsTheCanonicalErrorEnvelopeForAThrownException(): void
    {
        $subject = new class {
            use RespondsWithErrors;

            public function callErrorResponse(string $msg): array
            {
                return $this->errorResponse($msg);
            }
        };

        $result = $subject->callErrorResponse('something went wrong');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertTrue($result['error']);
    }

    public function testItPreservesTheExceptionMessageInTheErrorResponse(): void
    {
        $subject = new class {
            use RespondsWithErrors;

            public function callErrorResponse(string $msg): array
            {
                return $this->errorResponse($msg);
            }
        };

        $message = 'the exception message';
        $result = $subject->callErrorResponse($message);

        $this->assertSame($message, $result['message']);
    }

    public function testItReturnsTheCallableResultUnchangedWhenNoExceptionIsThrown(): void
    {
        $subject = new class {
            use RespondsWithErrors;

            /**
             * @param callable(): array<string, mixed> $fn
             * @return array<string, mixed>
             */
            public function callRunGuarded(callable $fn): array
            {
                return $this->runGuarded($fn);
            }
        };

        $expected = ['data' => 'value', 'count' => 42];
        $result = $subject->callRunGuarded(fn() => $expected);

        $this->assertSame($expected, $result);
    }

    public function testItMapsAThrowableFromTheGuardedCallableToTheErrorEnvelope(): void
    {
        $subject = new class {
            use RespondsWithErrors;

            /**
             * @param callable(): array<string, mixed> $fn
             * @return array<string, mixed>
             */
            public function callRunGuarded(callable $fn): array
            {
                return $this->runGuarded($fn);
            }
        };

        $message = 'exception in callable';
        $result = $subject->callRunGuarded(function () use ($message): array {
            throw new \RuntimeException($message);
        });

        $this->assertTrue($result['error']);
        $this->assertSame($message, $result['message']);
    }

    public function testItMergesExtraFieldsIntoTheErrorEnvelope(): void
    {
        $subject = new class {
            use RespondsWithErrors;

            /**
             * @param array<string, mixed> $extra
             * @return array<string, mixed>
             */
            public function callErrorResponse(string $msg, array $extra = []): array
            {
                return $this->errorResponse($msg, $extra);
            }
        };

        $result = $subject->callErrorResponse('execution failed', ['mode' => 'developer']);

        $this->assertSame(
            ['error' => true, 'message' => 'execution failed', 'mode' => 'developer'],
            $result
        );
    }

    public function testItProducesTheSameEnvelopeShapeTheToolsPreviouslyReturned(): void
    {
        $subject = new class {
            use RespondsWithErrors;

            public function callErrorResponse(string $msg): array
            {
                return $this->errorResponse($msg);
            }
        };

        $result = $subject->callErrorResponse('test message');

        $this->assertCount(2, $result);
        $this->assertSame(['error' => true, 'message' => 'test message'], $result);
    }
}
