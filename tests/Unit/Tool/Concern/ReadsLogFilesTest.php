<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Concern {
    /**
     * Namespaced override for filesize() — returns false when $forceFalsFilesize is set.
     */
    function filesize(string $path): int|false
    {
        if (\Inchoo\MagentoBricklayer\Tests\Unit\Tool\Concern\FilesizeStub::$returnFalse) {
            return false;
        }
        return \filesize($path);
    }
}

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool\Concern {
    use PHPUnit\Framework\TestCase;

    /**
     * Stub state carrier for the filesize() namespace override.
     */
    // phpcs:ignore PSR1.Classes.ClassDeclaration
    final class FilesizeStub
    {
        public static bool $returnFalse = false;
    }

    // phpcs:ignore PSR1.Classes.ClassDeclaration
    class ReadsLogFilesTest extends TestCase
    {
        private object $subject;

        protected function setUp(): void
        {
            FilesizeStub::$returnFalse = false;

            $this->subject = new class {
                use \Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ReadsLogFiles;

                public function callReadLastLines(string $path, int $lines): array
                {
                    return $this->readLastLines($path, $lines);
                }
            };
        }

        protected function tearDown(): void
        {
            FilesizeStub::$returnFalse = false;
        }

        public function testItReadsLastLinesWithoutThrowingWhenFilesizeReturnsFalse(): void
        {
            // Create a real file so fopen() succeeds
            $tmpFile = tempnam(sys_get_temp_dir(), 'bricklayer_test_');
            file_put_contents($tmpFile, "line1\nline2\nline3\n");

            FilesizeStub::$returnFalse = true;

            try {
                /** @phpstan-ignore-next-line */
                $result = $this->subject->callReadLastLines($tmpFile, 10);
                $this->assertIsArray($result);
                $this->assertEmpty($result, 'Expected empty array when filesize() returns false');
            } finally {
                FilesizeStub::$returnFalse = false;
                unlink($tmpFile);
            }
        }
    }
}
