<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Exception;

class ToolException extends BricklayerException
{
    public static function notFound(string $toolName): self
    {
        return new self("Tool not found: $toolName");
    }

    public static function invalidArguments(string $toolName, string $reason): self
    {
        return new self("Invalid arguments for tool '$toolName': $reason");
    }

    public static function executionFailed(string $toolName, string $reason, ?\Throwable $previous = null): self
    {
        return new self("Tool '$toolName' execution failed: $reason", 0, $previous);
    }

    public static function disabled(string $toolName, ?string $reason = null): self
    {
        $message = "Tool '$toolName' is disabled";
        if ($reason !== null) {
            $message .= ": $reason";
        }
        return new self($message);
    }

    public static function magentoNotInitialized(): self
    {
        return new self('Magento not initialized');
    }
}
