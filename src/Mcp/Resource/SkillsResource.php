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
 * Provides Magento development skills documentation as MCP resources.
 *
 * Skills are auto-discovered from the config/skills/ directory.
 * Adding a new skill requires no code changes — just create the directory with SKILL.md.
 */
class SkillsResource
{
    use FileLoaderTrait;

    /**
     * Dynamic skill access via URI template.
     * Handles: magento://skills/plugin, magento://skills/eav-development, etc.
     */
    #[McpResourceTemplate(
        uriTemplate: 'magento://skills/{name}',
        name: 'skill',
        description: 'Load a Magento development skill by name',
        mimeType: 'text/markdown'
    )]
    public function getSkill(string $name): string
    {
        return $this->loadConfigFile('skills', "{$name}/SKILL.md");
    }

    /**
     * Returns available skills list.
     *
     * @return string Markdown content with skills index
     */
    #[McpResource(
        uri: 'magento://skills/index',
        name: 'skills_index',
        description: 'Index of all available Magento 2 development skills',
        mimeType: 'text/markdown'
    )]
    public function getSkillsIndex(): string
    {
        $skillsDir = $this->getSkillsDir();
        $skills = [];

        // Each skill is a sub-directory containing a SKILL.md. Reuse the shared markdown
        // scanner (which guards against missing/unreadable dirs) and keep only SKILL.md files.
        foreach ($this->collectMarkdownRelativePaths($skillsDir) as $relativePath) {
            if (!str_ends_with($relativePath, '/SKILL.md')) {
                continue;
            }
            $skillName = dirname($relativePath);
            $content = file_get_contents($skillsDir . '/' . $relativePath);
            // Extract first heading
            if ($content !== false && preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                $skills[$skillName] = $matches[1];
            } else {
                $skills[$skillName] = $this->titleCaseName($skillName);
            }
        }

        ksort($skills);

        $markdown = "# Available Magento Development Skills\n\n";

        if (empty($skills)) {
            $markdown .= "No skills found in config/skills directory.\n";
        } else {
            foreach ($skills as $skillId => $skillTitle) {
                $markdown .= "- **{$skillTitle}** (`magento://skills/{$skillId}`)\n";
            }
        }

        return $markdown;
    }

    /**
     * Returns the absolute path to the skills configuration directory.
     *
     * Extracted as a protected method so that tests can substitute a temporary
     * directory without touching the filesystem layout expected by the real package.
     */
    protected function getSkillsDir(): string
    {
        return dirname(__DIR__, 3) . '/config/skills';
    }
}
