<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Concern;

trait FiltersFields
{
    private function filterFields(array $data, string $fields): array
    {
        if ($fields === '') {
            return $data;
        }

        $requested = array_map('trim', explode(',', $fields));
        return array_intersect_key($data, array_flip($requested));
    }
}
