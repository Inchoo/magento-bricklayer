<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Concern;

/**
 * Centralizes the verbosity enum validation used by ModuleTools, EavTools, and DiagnosticTools.
 *
 * Call requireValidVerbosity($verbosity) at the start of any public method that
 * accepts a verbosity parameter; return the error early when it is non-null.
 */
trait RequiresValidVerbosity
{
    /**
     * Returns null when $verbosity is valid, or the canonical error envelope otherwise.
     *
     * @return array{error: true, message: string}|null
     */
    private function requireValidVerbosity(string $verbosity): ?array
    {
        if (!in_array($verbosity, ['minimal', 'standard', 'detailed'], true)) {
            return ['error' => true, 'message' => 'verbosity must be one of: minimal, standard, detailed'];
        }

        return null;
    }
}
