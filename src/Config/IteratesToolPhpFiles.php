<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Config;

/**
 * Provides a shared PHP-file iterator over the Mcp/Tool directory tree.
 *
 * Used by ConfigValidator::getKnownTools() and ConfigInitializer::discoverConfigurableTools()
 * so both walkers use one canonical traversal instead of duplicating the
 * RecursiveIteratorIterator setup.
 */
trait IteratesToolPhpFiles
{
    /**
     * Iterate over every *.php file under $toolDir (recursively, skipping dots).
     *
     * Yields absolute pathname strings.
     * Returns an empty iterable when $toolDir does not exist or cannot be opened.
     *
     * @param string $toolDir Absolute path to the root of the tool tree.
     * @return iterable<\SplFileInfo>
     */
    private static function iterateToolPhpFiles(string $toolDir): iterable
    {
        if (!is_dir($toolDir)) {
            return;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($toolDir, \FilesystemIterator::SKIP_DOTS)
            );
        } catch (\UnexpectedValueException) {
            return;
        }

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }
}
