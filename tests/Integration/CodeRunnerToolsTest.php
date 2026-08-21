<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Integration;

use Inchoo\MagentoBricklayer\Mcp\Tool\CodeRunnerTools;

final class CodeRunnerToolsTest extends IntegrationTestCase
{
    public function testExecutesCodeThroughPsysh(): void
    {
        $result = (new CodeRunnerTools())->execute('return 1 + 1;');

        self::assertToolSuccess($result);
        self::assertSame(true, $result['success'] ?? null);
        self::assertSame(2, $result['return'] ?? null);
        self::assertSame('psysh', $result['runtime'] ?? null, 'PsySH is a hard requirement, not a fallback');
    }

    public function testHelpersResolveLiveMagentoServices(): void
    {
        $code = "return get('Magento\\\\Framework\\\\App\\\\ProductMetadataInterface')->getVersion();";
        $result = (new CodeRunnerTools())->execute($code);

        self::assertToolSuccess($result);
        self::assertSame(true, $result['success'] ?? null);
        self::assertMatchesRegularExpression('/^\d+\.\d+/', self::stringValue($result, 'return'));
    }

    public function testWriteFlagIsDoubleGatedByConfig(): void
    {
        $result = (new CodeRunnerTools())->execute('return 1;', '', true);

        self::assertToolSuccess($result);
        self::assertSame(true, $result['read_only'] ?? null);
        self::assertSame(
            true,
            $result['write_blocked_by_config'] ?? null,
            'allow_write=true without the config counterpart must be reported as blocked by config'
        );
    }

    public function testDangerousPatternsAreRejectedStatically(): void
    {
        $result = (new CodeRunnerTools())->execute('exec("id");');

        self::assertSame(true, $result['error'] ?? null);
    }
}
