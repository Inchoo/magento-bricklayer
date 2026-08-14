<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Guidelines;

/**
 * Shared helpers for local project override discovery.
 *
 * Used by GuidelinesCompiler, ContextTools, SearchTools, and the docs index
 * builder to parse YAML frontmatter from local SKILL.md files and to strip
 * that frontmatter before content is presented to agents or indexed.
 */
final class LocalOverrideHelper
{
    /**
     * Parse optional YAML frontmatter from a skill file.
     *
     * Only `name` and `description` keys are recognised. Any other keys are
     * ignored. If the file has no frontmatter or is unreadable, both values
     * in the returned array are null.
     *
     * @return array{name: ?string, description: ?string}
     */
    public static function parseSkillFrontmatter(string $filePath): array
    {
        $name = null;
        $description = null;

        if (!file_exists($filePath)) {
            return ['name' => $name, 'description' => $description];
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return ['name' => $name, 'description' => $description];
        }

        $trimmed = ltrim($content);
        if (!str_starts_with($trimmed, '---')) {
            return ['name' => $name, 'description' => $description];
        }

        if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $trimmed, $matches)) {
            $yaml = $matches[1];
            if (preg_match('/^name:\s*(.+)$/m', $yaml, $m)) {
                $name = trim($m[1]);
            }
            if (preg_match('/^description:\s*(.+)$/m', $yaml, $m)) {
                $description = trim($m[1]);
            }
        }

        return ['name' => $name, 'description' => $description];
    }

    /**
     * Strip an optional leading YAML frontmatter block from markdown content.
     *
     * Files without frontmatter are returned unchanged.
     */
    public static function stripFrontmatter(string $content): string
    {
        $trimmed = ltrim($content);
        if (!str_starts_with($trimmed, '---')) {
            return $content;
        }

        $pattern = '/^---\s*\n.*?\n---\s*\n/s';
        $result = preg_replace($pattern, '', $trimmed);
        if ($result === null) {
            return $content;
        }

        return ltrim($result);
    }

    /**
     * Derive a human-readable display name from a skill category directory name.
     *
     * Example: "csp-scripts" → "Csp Scripts".
     */
    public static function defaultDisplayName(string $category): string
    {
        return ucwords(str_replace('-', ' ', $category));
    }
}
