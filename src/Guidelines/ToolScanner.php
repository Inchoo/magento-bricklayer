<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Guidelines;

use Mcp\Capability\Attribute\McpTool;

/**
 * Scans tool classes for #[McpTool] attributes and returns the total count.
 *
 * Used by GuidelinesCompiler to report the number of available tools.
 */
class ToolScanner
{
    private const TOOL_NAMESPACE = 'Inchoo\\MagentoBricklayer\\Mcp\\Tool\\';

    /**
     * Scan tool directory and count #[McpTool] attributes.
     *
     * @return array{totalCount: int}
     */
    public function scan(): array
    {
        $toolDir = dirname(__DIR__) . '/Mcp/Tool';
        $totalCount = 0;

        foreach ($this->globToolFiles($toolDir . '/*.php') ?: [] as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fqcn = self::TOOL_NAMESPACE . $className;

            if (!class_exists($fqcn)) {
                require_once $file;
                if (!class_exists($fqcn)) {
                    continue;
                }
            }

            $ref = new \ReflectionClass($fqcn);

            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attrs = $method->getAttributes(McpTool::class);
                if (!empty($attrs)) {
                    $totalCount++;
                }
            }
        }

        return ['totalCount' => $totalCount];
    }

    /**
     * Return the list of files matching the given glob pattern.
     *
     * Extracted as a protected method to allow test doubles to inject a
     * controlled return value (including `false`) without touching the
     * filesystem.
     *
     * @return array<string>|false
     */
    protected function globToolFiles(string $pattern): array|false
    {
        return glob($pattern);
    }
}
