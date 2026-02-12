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
        $skillsDir = dirname(__DIR__, 3) . '/config/skills';
        $skills = [];

        if (is_dir($skillsDir)) {
            $dirs = glob($skillsDir . '/*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $skillName = basename($dir);
                $skillFile = $dir . '/SKILL.md';
                if (file_exists($skillFile)) {
                    $content = file_get_contents($skillFile);
                    // Extract first heading
                    if ($content && preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                        $skills[$skillName] = $matches[1];
                    } else {
                        $skills[$skillName] = ucwords(str_replace('-', ' ', $skillName));
                    }
                }
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
}
