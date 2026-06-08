<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Support;

/**
 * Shared, namespace-neutral recursive .md directory scanner.
 *
 * Single implementation of the "walk a directory, return forward-slash-normalised relative
 * paths of every .md file" loop used by the MCP resources, SearchTools, and GuidelinesCompiler.
 */
trait CollectsMarkdownFiles
{
    /**
     * Recursively collect all .md file paths relative to $absDir.
     *
     * Returns forward-slash-normalised paths (e.g. "patterns/plugin.md").
     * Returns an empty array when the directory does not exist or is unreadable.
     *
     * @param string $absDir Absolute path to the directory to scan.
     * @return list<string>
     */
    private function collectMarkdownRelativePaths(string $absDir): array
    {
        if (!is_dir($absDir)) {
            return [];
        }

        $realDir = realpath($absDir);
        if ($realDir === false) {
            return [];
        }
        $realDir = rtrim($realDir, '/\\') . '/';

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($realDir, \FilesystemIterator::SKIP_DOTS)
            );
        } catch (\UnexpectedValueException) {
            return [];
        }

        $paths = [];
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($realDir)));
            if ($relative !== '') {
                $paths[] = $relative;
            }
        }

        return $paths;
    }
}
