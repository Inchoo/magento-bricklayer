<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for response enhancement patterns used across list tools.
 *
 * The `has_more` pagination formula is inline in list methods (e.g. CatalogTools::listProducts,
 * OrderTools::listOrders) as: ($currentPage * $pageSize) < $totalCount
 *
 * The `_hint` logic in CatalogTools::getProduct is tightly coupled to Magento EAV objects
 * and requires integration testing.
 */
class ResponseEnhancementsTest extends TestCase
{
    /**
     * Verifies the has_more formula used in list tool responses.
     */
    #[DataProvider('hasMoreProvider')]
    public function testHasMorePaginationFormula(
        int $currentPage,
        int $pageSize,
        int $totalCount,
        bool $expected
    ): void {
        $hasMore = ($currentPage * $pageSize) < $totalCount;

        $this->assertSame(
            $expected,
            $hasMore,
            sprintf(
                'has_more for page=%d, pageSize=%d, total=%d should be %s',
                $currentPage,
                $pageSize,
                $totalCount,
                $expected ? 'true' : 'false'
            )
        );
    }

    /**
     * @return array<string, array{int, int, int, bool}>
     */
    public static function hasMoreProvider(): array
    {
        return [
            'more pages exist'       => [1, 10, 25, true],
            'last page'              => [3, 10, 25, false],
            'exact fit'              => [2, 10, 20, false],
            'single page'            => [1, 10,  5, false],
            'first of two pages'     => [1, 10, 11, true],
            'second of two pages'    => [2, 10, 11, false],
            'large page size'        => [1, 100, 50, false],
            'page size of one'       => [1,  1,  2, true],
        ];
    }

    /**
     * The _hint logic in CatalogTools::getProduct checks for user-defined EAV attributes
     * on a loaded product and adds a hint string. This requires a real Magento product
     * instance and EAV config — it cannot be unit tested without Magento bootstrap.
     */
    public function testHintLogicRequiresIntegrationTest(): void
    {
        $this->markTestSkipped(
            '_hint logic in CatalogTools::getProduct is coupled to Magento EAV objects; requires integration test.'
        );
    }
}
