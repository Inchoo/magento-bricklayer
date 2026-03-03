<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool\Concern;

use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\FiltersFields;
use PHPUnit\Framework\TestCase;

class FiltersFieldsTest extends TestCase
{
    private object $wrapper;

    protected function setUp(): void
    {
        $this->wrapper = new class {
            use FiltersFields;

            public function callFilterFields(array $data, string $fields): array
            {
                return $this->filterFields($data, $fields);
            }
        };
    }

    public function testFilterFieldsReturnsRequestedFieldsOnly(): void
    {
        $data = ['sku' => 'ABC', 'name' => 'Widget', 'price' => 9.99, 'status' => 1];
        $result = $this->wrapper->callFilterFields($data, 'sku,name');

        $this->assertArrayHasKey('sku', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('price', $result);
        $this->assertArrayNotHasKey('status', $result);
    }

    public function testFilterFieldsWithEmptyStringReturnsOriginal(): void
    {
        $data = ['sku' => 'ABC', 'name' => 'Widget'];
        $result = $this->wrapper->callFilterFields($data, '');

        $this->assertSame($data, $result);
    }

    public function testFilterFieldsHandlesNestedArrays(): void
    {
        $data = [
            'sku' => 'ABC',
            'name' => 'Widget',
            'extension_attributes' => ['stock' => 10, 'warehouse' => 'US'],
        ];
        $result = $this->wrapper->callFilterFields($data, 'sku,extension_attributes');

        $this->assertArrayHasKey('sku', $result);
        $this->assertArrayHasKey('extension_attributes', $result);
        $this->assertArrayNotHasKey('name', $result);
        // Nested array is returned as-is (only top-level keys are filtered)
        $this->assertSame(['stock' => 10, 'warehouse' => 'US'], $result['extension_attributes']);
    }

    public function testFilterFieldsIgnoresNonExistentFieldNames(): void
    {
        $data = ['sku' => 'ABC', 'name' => 'Widget'];
        $result = $this->wrapper->callFilterFields($data, 'sku,nonexistent,also_missing');

        $this->assertArrayHasKey('sku', $result);
        $this->assertArrayNotHasKey('nonexistent', $result);
        $this->assertArrayNotHasKey('also_missing', $result);
        $this->assertCount(1, $result);
    }
}
