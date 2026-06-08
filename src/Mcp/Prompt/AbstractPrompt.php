<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Prompt;

/**
 * Base class for MCP prompt providers.
 *
 * Provides a shared helper to build the canonical prompt-message envelope.
 * The canonical content shape is the typed MCP-spec form:
 *   [['role' => 'user', 'content' => ['type' => 'text', 'text' => $text]]]
 *
 * All concrete subclasses retain their own #[McpPrompt]-annotated methods;
 * the SDK Discoverer only indexes methods declared directly on the concrete
 * class, so this abstract base is transparent to prompt discovery.
 */
abstract class AbstractPrompt
{
    /**
     * Builds the canonical single-message prompt envelope for a user message.
     *
     * @param string $text The prompt text content
     * @return array<array<string, mixed>> A single-element array containing the user message
     */
    protected function userMessage(string $text): array
    {
        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
        ];
    }
}
