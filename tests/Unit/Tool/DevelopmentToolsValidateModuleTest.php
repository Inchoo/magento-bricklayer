<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\DevelopmentTools;
use PHPUnit\Framework\TestCase;

class DevelopmentToolsValidateModuleTest extends TestCase
{
    /**
     * Invoke a private method on DevelopmentTools via reflection.
     *
     * DevelopmentTools::__construct() would require Magento bootstrap,
     * so we use newInstanceWithoutConstructor() for tests that exercise private helpers.
     */
    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionClass(DevelopmentTools::class);
        $m = $ref->getMethod($method);

        $instance = $ref->newInstanceWithoutConstructor();
        return $m->invoke($instance, ...$args);
    }

    /**
     * Build a temp module directory tree.
     * Returns the temp dir path.
     *
     * @param array<string, string> $files  map of relative-path => file-content
     */
    private function buildTempModule(array $files): string
    {
        $base = sys_get_temp_dir() . '/bricklayer_test_' . uniqid('', true);
        mkdir($base, 0777, true);

        foreach ($files as $relPath => $content) {
            $full = $base . '/' . $relPath;
            $dir  = dirname($full);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($full, $content);
        }

        return $base;
    }

    /**
     * Clean up a temp directory recursively.
     */
    private function removeTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir((string)$item) : unlink((string)$item);
        }
        rmdir($dir);
    }

    // ── collectPhpFiles ───────────────────────────────────────────────────

    public function testItFindsPhpFilesNestedMoreThanOneDirectoryDeep(): void
    {
        $base = $this->buildTempModule([
            'Model/ResourceModel/Collection/SomeCollection.php' => '<?php declare(strict_types=1);',
        ]);

        try {
            /** @var string[] $files */
            $files = $this->invokePrivate('collectPhpFiles', $base);
            $this->assertNotEmpty($files, 'Expected at least one PHP file to be collected');
            $this->assertCount(1, $files);
            $this->assertStringEndsWith('SomeCollection.php', $files[0]);
        } finally {
            $this->removeTempDir($base);
        }
    }

    public function testItFlagsADeeplyNestedFileMissingStrictTypes(): void
    {
        $base = $this->buildTempModule([
            'Model/ResourceModel/Collection/SomeCollection.php' => '<?php',
        ]);

        try {
            /** @var string[] $files */
            $files = $this->invokePrivate('collectPhpFiles', $base);
            $this->assertNotEmpty($files);

            $missing = 0;
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if ($content !== false && !str_contains($content, 'declare(strict_types=1)')) {
                    $missing++;
                }
            }
            $this->assertSame(1, $missing, 'Expected exactly one file missing strict_types');
        } finally {
            $this->removeTempDir($base);
        }
    }

    public function testItStillScansTopLevelPhpFiles(): void
    {
        $base = $this->buildTempModule([
            'registration.php' => '<?php declare(strict_types=1);',
        ]);

        try {
            /** @var string[] $files */
            $files = $this->invokePrivate('collectPhpFiles', $base);
            $this->assertNotEmpty($files);
            $this->assertStringEndsWith('registration.php', $files[0]);
        } finally {
            $this->removeTempDir($base);
        }
    }

    public function testItReportsACleanModuleWhenAllNestedFilesDeclareStrictTypes(): void
    {
        $base = $this->buildTempModule([
            'registration.php'                               => '<?php declare(strict_types=1);',
            'Model/ResourceModel/SomeResource.php'           => '<?php declare(strict_types=1);',
            'Block/Adminhtml/Edit/Form.php'                  => '<?php declare(strict_types=1);',
        ]);

        try {
            /** @var string[] $files */
            $files = $this->invokePrivate('collectPhpFiles', $base);
            $this->assertCount(3, $files);

            $missing = 0;
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if ($content !== false && !str_contains($content, 'declare(strict_types=1)')) {
                    $missing++;
                }
            }
            $this->assertSame(0, $missing, 'Expected zero files missing strict_types in a clean module');
        } finally {
            $this->removeTempDir($base);
        }
    }
}
