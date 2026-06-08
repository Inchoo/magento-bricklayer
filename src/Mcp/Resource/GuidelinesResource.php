<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;

/**
 * Provides Magento development guidelines as MCP resources.
 *
 * Guidelines are auto-discovered from the config/guidelines/ directory.
 * Adding a new guideline file requires no code changes — just drop the .md file.
 */
class GuidelinesResource
{
    use FileLoaderTrait;

    /**
     * Dynamic guideline access via URI template.
     * Handles: magento://guidelines/core/security, magento://guidelines/patterns/plugin, etc.
     */
    #[McpResourceTemplate(
        uriTemplate: 'magento://guidelines/{category}/{name}',
        name: 'guideline',
        description: 'Load a Magento development guideline by category and name',
        mimeType: 'text/markdown'
    )]
    public function getGuideline(string $category, string $name): string
    {
        return $this->loadConfigFile('guidelines', "{$category}/{$name}.md");
    }

    /**
     * Dynamic index — scans config/guidelines/ directory.
     * Returns list of all available guidelines with paths.
     */
    #[McpResource(
        uri: 'magento://guidelines/index',
        name: 'guidelines_index',
        description: 'Index of all available Magento 2 development guidelines',
        mimeType: 'text/markdown'
    )]
    public function getGuidelinesIndex(): string
    {
        $guidelinesDir = $this->getGuidelinesDir();
        $guidelines = [];

        foreach ($this->collectMarkdownRelativePaths($guidelinesDir) as $relativePath) {
            $pathWithoutExt = preg_replace('/\.md$/', '', $relativePath);
            $parts = explode('/', (string) $pathWithoutExt);

            if (count($parts) !== 2) {
                continue;
            }

            [$category, $name] = $parts;

            $content = file_get_contents($guidelinesDir . '/' . $relativePath);
            if ($content !== false && preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                $title = $matches[1];
            } else {
                $title = $this->titleCaseName($name);
            }

            $guidelines[$category][] = [
                'name' => $name,
                'title' => $title,
                'uri' => "magento://guidelines/{$category}/{$name}",
            ];
        }

        ksort($guidelines);

        $markdown = "# Available Magento Development Guidelines\n\n";

        if (empty($guidelines)) {
            $markdown .= "No guidelines found in config/guidelines directory.\n";
        } else {
            foreach ($guidelines as $category => $items) {
                $markdown .= "## " . $this->titleCaseName($category) . "\n\n";
                usort($items, fn(array $a, array $b) => $a['name'] <=> $b['name']);
                foreach ($items as $item) {
                    $markdown .= "- **{$item['title']}** (`{$item['uri']}`)\n";
                }
                $markdown .= "\n";
            }
        }

        return $markdown;
    }

    /**
     * Returns the absolute path to the guidelines configuration directory.
     *
     * Extracted as a protected method so that tests can substitute a temporary
     * directory without touching the filesystem layout expected by the real package.
     */
    protected function getGuidelinesDir(): string
    {
        return dirname(__DIR__, 3) . '/config/guidelines';
    }
}
