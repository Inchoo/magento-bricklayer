<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Concern;

trait PaginatesResults
{
    /**
     * Build the canonical paginated list envelope.
     *
     * @param array<int|string, mixed> $items
     * @return array{total_count: int, page_size: int, current_page: int, has_more: bool,
     *               items: array<int|string, mixed>}
     */
    private function paginatedResponse(
        int $total,
        int $pageSize,
        int $currentPage,
        array $items
    ): array {
        return [
            'total_count' => $total,
            'page_size' => $pageSize,
            'current_page' => $currentPage,
            'has_more' => ($currentPage * $pageSize) < $total,
            'items' => $items,
        ];
    }

    /**
     * Build the canonical count-only short-circuit response.
     *
     * @param array<string, mixed> $extra Additional fields to merge into the response.
     * @return array<string, mixed>
     */
    private function countOnlyResponse(int $total, array $extra = []): array
    {
        return array_merge(
            [
                'total' => $total,
                'count_only' => true,
            ],
            $extra
        );
    }
}
