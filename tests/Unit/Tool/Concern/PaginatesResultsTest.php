<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool\Concern;

use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\PaginatesResults;
use PHPUnit\Framework\TestCase;

class PaginatesResultsTest extends TestCase
{
    public function testItBuildsAPaginatedResponseWithTotalPageSizeAndCurrentPage(): void
    {
        $subject = new class {
            use PaginatesResults;

            public function callPaginatedResponse(
                int $total,
                int $pageSize,
                int $currentPage,
                array $items
            ): array {
                return $this->paginatedResponse($total, $pageSize, $currentPage, $items);
            }
        };

        $result = $subject->callPaginatedResponse(100, 20, 1, [['id' => 1]]);

        $this->assertArrayHasKey('total_count', $result);
        $this->assertArrayHasKey('page_size', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertArrayHasKey('items', $result);

        $this->assertSame(100, $result['total_count']);
        $this->assertSame(20, $result['page_size']);
        $this->assertSame(1, $result['current_page']);
        $this->assertSame([['id' => 1]], $result['items']);
    }

    public function testItComputesHasMoreTrueWhenMorePagesRemain(): void
    {
        $subject = new class {
            use PaginatesResults;

            public function callPaginatedResponse(
                int $total,
                int $pageSize,
                int $currentPage,
                array $items
            ): array {
                return $this->paginatedResponse($total, $pageSize, $currentPage, $items);
            }
        };

        // page 1 of 5 (total=100, pageSize=20) → 1*20=20 < 100 → true
        $result = $subject->callPaginatedResponse(100, 20, 1, []);

        $this->assertTrue($result['has_more']);
    }

    public function testItComputesHasMoreFalseOnTheLastPage(): void
    {
        $subject = new class {
            use PaginatesResults;

            public function callPaginatedResponse(
                int $total,
                int $pageSize,
                int $currentPage,
                array $items
            ): array {
                return $this->paginatedResponse($total, $pageSize, $currentPage, $items);
            }
        };

        // page 5 of 5 (total=100, pageSize=20) → 5*20=100 < 100 → false
        $result = $subject->callPaginatedResponse(100, 20, 5, []);

        $this->assertFalse($result['has_more']);
    }

    public function testItBuildsACountOnlyResponseWithExtraFieldsMerged(): void
    {
        $subject = new class {
            use PaginatesResults;

            public function callCountOnlyResponse(int $total, array $extra = []): array
            {
                return $this->countOnlyResponse($total, $extra);
            }
        };

        $result = $subject->callCountOnlyResponse(42, ['filter' => 'pending']);

        $this->assertSame(42, $result['total']);
        $this->assertTrue($result['count_only']);
        $this->assertSame('pending', $result['filter']);
    }

    public function testItReturnsTheSameEnvelopeShapeTheListToolsPreviouslyReturned(): void
    {
        $subject = new class {
            use PaginatesResults;

            public function callPaginatedResponse(
                int $total,
                int $pageSize,
                int $currentPage,
                array $items
            ): array {
                return $this->paginatedResponse($total, $pageSize, $currentPage, $items);
            }
        };

        $items = [['sku' => 'ABC'], ['sku' => 'DEF']];
        $result = $subject->callPaginatedResponse(50, 10, 2, $items);

        $expected = [
            'total_count' => 50,
            'page_size' => 10,
            'current_page' => 2,
            'has_more' => true,  // 2*10=20 < 50
            'items' => $items,
        ];

        $this->assertSame($expected, $result);
        // Verify key order matches existing tool envelopes
        $this->assertSame(array_keys($expected), array_keys($result));
    }
}
