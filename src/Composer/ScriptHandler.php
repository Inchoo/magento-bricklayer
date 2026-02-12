<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Composer;

use Composer\Script\Event;
use Inchoo\MagentoBricklayer\Bootstrap\MagentoDetector;

/**
 * Composer script handler for post-install and post-update events.
 *
 * Creates the documentation index directory automatically when the package
 * is installed or updated via Composer.
 */
class ScriptHandler
{
    public static function createDocIndex(Event $event): void
    {
        $io = $event->getIO();
        $detector = new MagentoDetector();
        $magentoRoot = $detector->detect();

        if ($magentoRoot === null) {
            $io->write('<warning>Bricklayer: Could not detect Magento root, skipping doc index creation.</warning>');
            return;
        }

        $indexPath = $magentoRoot . '/.bricklayer/docs-index';

        try {
            if (!is_dir($indexPath)) {
                mkdir($indexPath, 0755, true);
            }

            $indexFile = $indexPath . '/index.json';
            $indexData = [
                'version' => '1.0.0',
                'updated_at' => date('c'),
                'documents' => 0,
                'status' => 'placeholder',
            ];

            file_put_contents($indexFile, json_encode($indexData, JSON_PRETTY_PRINT));
            $io->write('<info>Bricklayer: Documentation index created at .bricklayer/docs-index/</info>');
        } catch (\Throwable $e) {
            $io->write('<warning>Bricklayer: Failed to create doc index: ' . $e->getMessage() . '</warning>');
        }
    }
}
