<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Resource;

use Inchoo\MagentoBricklayer\Mcp\Resource\SkillsResource;
use PHPUnit\Framework\TestCase;

/**
 * Tests for B27: SkillsResource::getSkillsIndex must not warn/crash on a missing or
 * unreadable skills directory; it must fall through to "No skills found".
 *
 * The directory scan now routes through FileLoaderTrait::collectMarkdownRelativePaths(),
 * which returns [] for a missing/unreadable directory (its error guards are covered by
 * MarkdownScanHelperTest), so the index degrades gracefully.
 *
 * Integration note: the production skills directory path is resolved relative to the
 * installed package source and is integration-only; these tests exercise the logic
 * via subclasses that inject an arbitrary skills directory.
 */
class SkillsResourceTest extends TestCase
{
    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bricklayer_skills_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    /**
     * When the skills directory does not exist (or is unreadable), getSkillsIndex must
     * gracefully fall through to "No skills found" instead of warning/crashing.
     */
    public function testItReturnsAnEmptySkillsIndexWhenTheDirectoryIsMissing(): void
    {
        $subject = $this->makeSubjectWithDir($this->tempDir . '/does-not-exist');

        $output = $subject->getSkillsIndex();

        $this->assertStringContainsString('No skills found', $output);
    }

    /**
     * When the skills directory exists and contains valid skill sub-directories,
     * getSkillsIndex lists them with name and URI.
     */
    public function testItListsSkillsNormallyWhenTheDirectoryIsReadable(): void
    {
        // Create a skill: a sub-directory with a SKILL.md file.
        $skillDir = $this->tempDir . '/my-skill';
        mkdir($skillDir, 0755, true);
        file_put_contents($skillDir . '/SKILL.md', "# My Cool Skill\n\nSome content here.");

        $subject = $this->makeSubjectWithDir($this->tempDir);

        $output = $subject->getSkillsIndex();

        $this->assertStringContainsString('My Cool Skill', $output);
        $this->assertStringContainsString('magento://skills/my-skill', $output);
    }

    /**
     * Nested sub-skills (a topic directory inside a skill directory) are loaded via the
     * dedicated {name}/{topic} template, since the SDK matches template variables as
     * single path segments.
     */
    public function testItLoadsANestedSubSkillByNameAndTopic(): void
    {
        $topicDir = $this->tempDir . '/parent-skill/some-topic';
        mkdir($topicDir, 0755, true);
        file_put_contents($topicDir . '/SKILL.md', "# Some Topic\n\nFocused content.");

        $subject = $this->makeSubjectWithDir($this->tempDir);

        $output = $subject->getSkillTopic('parent-skill', 'some-topic');

        $this->assertStringContainsString('Focused content.', $output);
    }

    /**
     * Authoring frontmatter must not reach the agent; both load paths strip it.
     */
    public function testItStripsFrontmatterFromSkillContent(): void
    {
        $skillDir = $this->tempDir . '/fm-skill';
        mkdir($skillDir, 0755, true);
        file_put_contents(
            $skillDir . '/SKILL.md',
            "---\nname: fm-skill\ndescription: internal metadata\n---\n\n# Frontmatter Skill\n\nBody."
        );

        $subject = $this->makeSubjectWithDir($this->tempDir);

        $output = $subject->getSkill('fm-skill');

        $this->assertStringStartsWith('# Frontmatter Skill', $output);
        $this->assertStringNotContainsString('internal metadata', $output);
    }

    /**
     * Template variables match [^/]+, which still admits ".."; traversal segments must
     * fall through to the placeholder instead of reading outside the skills directory.
     */
    public function testItRejectsPathTraversalSegments(): void
    {
        $subject = $this->makeSubjectWithDir($this->tempDir);

        $this->assertStringContainsString('not yet available', $subject->getSkill('..'));
        $this->assertStringContainsString('not yet available', $subject->getSkillTopic('..', 'guidelines'));
    }

    /**
     * A missing sub-skill degrades to the placeholder, mirroring getSkill behaviour.
     */
    public function testItReturnsAPlaceholderForAMissingSubSkill(): void
    {
        $subject = $this->makeSubjectWithDir($this->tempDir);

        $output = $subject->getSkillTopic('parent-skill', 'missing-topic');

        $this->assertStringContainsString('not yet available', $output);
    }

    /**
     * Regression guard: every magento://skills/... URI referenced inside the shipped
     * skill documentation must resolve to an existing SKILL.md, so skills never point
     * agents at dead resources.
     */
    public function testEveryShippedSkillUriReferenceResolvesToAFile(): void
    {
        $skillsDir = dirname(__DIR__, 3) . '/config/skills';
        $this->assertDirectoryExists($skillsDir);

        $unresolved = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($skillsDir));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getFilename() !== 'SKILL.md') {
                continue;
            }
            $content = (string) file_get_contents($file->getPathname());
            preg_match_all('#magento://skills/([a-z0-9-]+(?:/[a-z0-9-]+)?)#', $content, $matches);
            foreach (array_unique($matches[1]) as $reference) {
                if ($reference === 'index') {
                    continue;
                }
                if (!is_file($skillsDir . '/' . $reference . '/SKILL.md')) {
                    $unresolved[] = "{$file->getPathname()} -> magento://skills/{$reference}";
                }
            }
        }

        $this->assertSame([], $unresolved, 'Skill docs reference unresolvable skill URIs.');
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /**
     * A subclass that uses the given directory with normal scan behaviour.
     */
    private function makeSubjectWithDir(string $skillsDir): SkillsResource
    {
        return new class ($skillsDir) extends SkillsResource {
            public function __construct(private readonly string $skillsDir)
            {
            }

            protected function getSkillsDir(): string
            {
                return $this->skillsDir;
            }
        };
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff((array) scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
