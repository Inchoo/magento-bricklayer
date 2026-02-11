<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Resource;

/**
 * Provides common file loading functionality for MCP resources.
 */
trait FileLoaderTrait
{
    /**
     * Load content from a file within the config directory
     *
     * @param string $subDirectory The subdirectory within config (e.g., 'guidelines', 'skills')
     * @param string $relativePath The relative path within the subdirectory
     * @return string File content or placeholder message
     */
    private function loadConfigFile(string $subDirectory, string $relativePath): string
    {
        $filePath = dirname(__DIR__, 3) . '/config/' . $subDirectory . '/' . $relativePath;

        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            if ($content !== false) {
                return $content;
            }
        }

        return $this->generatePlaceholder($relativePath);
    }

    /**
     * Generate placeholder content for missing files
     *
     * @param string $path The file path (used to extract a title)
     * @return string Placeholder markdown content
     */
    private function generatePlaceholder(string $path): string
    {
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $title = ucwords(str_replace(['-', '_'], ' ', $filename));

        return <<<MARKDOWN
# {$title}

This documentation is not yet available.

Please refer to the official Magento DevDocs for guidance:
https://developer.adobe.com/commerce/php/development/
MARKDOWN;
    }
}
