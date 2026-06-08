<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool\Fixture;

use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\MasksSensitiveConfig;

/**
 * Test helper exposing private MasksSensitiveConfig trait methods for unit testing.
 */
final class MasksSensitiveConfigTestSubject
{
    use MasksSensitiveConfig;

    public function callIsSensitivePath(string $path): bool
    {
        return $this->isSensitivePath($path);
    }

    public function callMaskValue(): string
    {
        return $this->maskValue();
    }
}
