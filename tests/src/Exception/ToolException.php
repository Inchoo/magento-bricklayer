<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Exception;

/**
 * Exception thrown when an MCP tool operation fails
 */
class ToolException extends BricklayerException
{
    /**
     * Create exception for tool not found
     *
     * @param string $toolName The name of the tool
     * @return self
     */
    public static function notFound(string $toolName): self
    {
        return new self("Tool not found: $toolName");
    }

    /**
     * Create exception for invalid tool arguments
     *
     * @param string $toolName The name of the tool
     * @param string $reason The reason the arguments are invalid
     * @return self
     */
    public static function invalidArguments(string $toolName, string $reason): self
    {
        return new self("Invalid arguments for tool '$toolName': $reason");
    }

    /**
     * Create exception for tool execution failure
     *
     * @param string $toolName The name of the tool
     * @param string $reason The reason for failure
     * @param \Throwable|null $previous The previous exception
     * @return self
     */
    public static function executionFailed(string $toolName, string $reason, ?\Throwable $previous = null): self
    {
        return new self("Tool '$toolName' execution failed: $reason", 0, $previous);
    }

    /**
     * Create exception for tool disabled
     *
     * @param string $toolName The name of the tool
     * @param string|null $reason Optional reason why it's disabled
     * @return self
     */
    public static function disabled(string $toolName, ?string $reason = null): self
    {
        $message = "Tool '$toolName' is disabled";
        if ($reason !== null) {
            $message .= ": $reason";
        }
        return new self($message);
    }
}
