<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Resource;

use Inchoo\MagentoBricklayer\Mcp\Resource\FileLoaderTrait;
use PHPUnit\Framework\TestCase;

class FileLoaderTraitTest extends TestCase
{
    /**
     * it title-cases names with hyphens and underscores consistently
     */
    public function testItTitleCasesNamesWithHyphensAndUnderscoresConsistently(): void
    {
        $subject = new class {
            use FileLoaderTrait;

            public function callTitleCase(string $name): string
            {
                return $this->titleCaseName($name);
            }
        };

        // Hyphens should be replaced with spaces and title-cased
        $this->assertSame('My Skill', $subject->callTitleCase('my-skill'));
        // Underscores should also be replaced with spaces and title-cased
        $this->assertSame('My Skill', $subject->callTitleCase('my_skill'));
        // Mixed hyphens and underscores
        $this->assertSame('My Cool Skill', $subject->callTitleCase('my-cool_skill'));
        // Plain name
        $this->assertSame('Plugin', $subject->callTitleCase('plugin'));
    }

    public function testSkillsIndexUsesUnderscoreAwareTitleCase(): void
    {
        // SkillsResource::getSkillsIndex should use the shared helper that handles underscores
        $tempDir = sys_get_temp_dir() . '/bricklayer_title_test_' . uniqid();
        $skillDir = $tempDir . '/my_underscore_skill';
        mkdir($skillDir, 0755, true);
        // No heading in SKILL.md — will fall back to title-case helper
        file_put_contents($skillDir . '/SKILL.md', 'Some content without a heading.');

        try {
            $subject = new class ($tempDir) extends \Inchoo\MagentoBricklayer\Mcp\Resource\SkillsResource {
                public function __construct(private readonly string $skillsDir)
                {
                }

                protected function getSkillsDir(): string
                {
                    return $this->skillsDir;
                }
            };

            $output = $subject->getSkillsIndex();

            // Underscore should be replaced with a space in title-case fallback
            $this->assertStringContainsString('My Underscore Skill', $output);
        } finally {
            unlink($skillDir . '/SKILL.md');
            rmdir($skillDir);
            rmdir($tempDir);
        }
    }
}
