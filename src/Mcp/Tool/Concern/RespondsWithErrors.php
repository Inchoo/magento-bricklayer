<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Concern;

trait RespondsWithErrors
{
    /**
     * Build the canonical error envelope, optionally merging extra fields.
     *
     * @param array<string, mixed> $extra Additional fields merged into the envelope.
     * @return array<string, mixed>
     */
    private function errorResponse(string $msg, array $extra = []): array
    {
        return ['error' => true, 'message' => $msg] + $extra;
    }

    /**
     * Execute $fn and return its result; map any \Throwable to the canonical error envelope.
     *
     * @param callable(): array<string, mixed> $fn
     * @return array<string, mixed>
     */
    private function runGuarded(callable $fn): array
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
