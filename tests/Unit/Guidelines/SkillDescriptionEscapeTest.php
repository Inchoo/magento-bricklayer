<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Guidelines;

use Inchoo\MagentoBricklayer\Guidelines\GuidelinesCompiler;
use PHPUnit\Framework\TestCase;

class SkillDescriptionEscapeTest extends TestCase
{
    private string $magentoRoot;
    private string $packageRoot;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/bricklayer_escape_' . uniqid();
        $this->magentoRoot = $base . '/project';
        $this->packageRoot = $base . '/package';
        mkdir($this->magentoRoot, 0755, true);
        mkdir($this->packageRoot . '/config/guidelines/patterns', 0755, true);
        mkdir($this->packageRoot . '/config/skills', 0755, true);
        file_put_contents(
            $this->packageRoot . '/config/guidelines/patterns/plugin.md',
            '# Bundled Plugin Guideline'
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->magentoRoot));
    }

    public function testItEscapesAPipeCharacterInASkillDescriptionTableCell(): void
    {
        $skillDir = $this->magentoRoot . '/.bricklayer/skills/loyalty';
        mkdir($skillDir, 0755, true);
        file_put_contents(
            $skillDir . '/SKILL.md',
            "---\nname: Loyalty\ndescription: Earn | Redeem points\n---\n\n# Loyalty skill body."
        );

        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        $this->assertStringContainsString('Earn \\| Redeem points', $output);
    }

    public function testItPreservesADescriptionWithoutPipesUnchanged(): void
    {
        $skillDir = $this->magentoRoot . '/.bricklayer/skills/loyalty';
        mkdir($skillDir, 0755, true);
        file_put_contents(
            $skillDir . '/SKILL.md',
            "---\nname: Loyalty\ndescription: Earn and redeem points\n---\n\n# Loyalty skill body."
        );

        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        $this->assertStringContainsString('Earn and redeem points', $output);
        $this->assertStringNotContainsString('Earn and redeem points\\|', $output);
    }

    public function testItKeepsTheGeneratedTableRowWellFormedWhenTheDescriptionContainsPipes(): void
    {
        $skillDir = $this->magentoRoot . '/.bricklayer/skills/loyalty';
        mkdir($skillDir, 0755, true);
        file_put_contents(
            $skillDir . '/SKILL.md',
            "---\nname: Loyalty\ndescription: Earn | Redeem points\n---\n\n# Loyalty skill body."
        );

        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        $this->assertStringContainsString('| `loyalty` | Earn \\| Redeem points |', $output);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $scanned = scandir($dir);
        if ($scanned === false) {
            return;
        }
        $files = array_diff($scanned, ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
