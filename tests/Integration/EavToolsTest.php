<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Integration;

use Inchoo\MagentoBricklayer\Mcp\Tool\EavTools;

final class EavToolsTest extends IntegrationTestCase
{
    public function testProductAttributesAreReadFromTheDatabase(): void
    {
        $result = (new EavTools())->getEavAttributes('catalog_product', false, 'minimal');

        self::assertToolSuccess($result);
        self::assertSame('catalog_product', $result['entity_type'] ?? null);
        self::assertGreaterThan(30, $result['attribute_count'] ?? 0);

        $codes = [];
        foreach (self::arrayValue($result, 'attributes') as $attribute) {
            if (is_array($attribute) && is_string($attribute['attribute_code'] ?? null)) {
                $codes[] = $attribute['attribute_code'];
            }
        }

        self::assertContains('sku', $codes);
        self::assertContains('name', $codes);
    }

    public function testUnknownEntityTypeIsRejected(): void
    {
        $result = (new EavTools())->getEavAttributes('not_an_entity_type');

        self::assertSame(true, $result['error'] ?? null);
    }
}
