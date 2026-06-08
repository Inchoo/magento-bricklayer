<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool\Fixture;

/**
 * A minimal mock DB connection that records SQL queries and returns canned responses.
 *
 * Only implements the methods actually called by DatabaseTools::getTableSchema().
 */
class MockDbConnection
{
    /** @var array<int, array{method: string, sql: string, bindings: mixed}> */
    public array $calls = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $responses = [];

    /**
     * Pre-configure a canned response for queries containing a given SQL fragment.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function willReturn(string $sqlFragment, array $rows): void
    {
        $this->responses[$sqlFragment] = $rows;
    }

    /** @return array<int, string> */
    public function fetchCol(string $sql): array
    {
        $this->calls[] = ['method' => 'fetchCol', 'sql' => $sql, 'bindings' => []];
        foreach ($this->responses as $fragment => $rows) {
            if (str_contains($sql, $fragment)) {
                $firstKey = array_key_first($rows[0] ?? []);
                return $firstKey !== null ? array_column($rows, $firstKey) : [];
            }
        }
        return [];
    }

    /**
     * @param array<mixed> $bindings
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $bindings = []): array
    {
        $this->calls[] = ['method' => 'fetchAll', 'sql' => $sql, 'bindings' => $bindings];
        foreach ($this->responses as $fragment => $rows) {
            if (str_contains($sql, $fragment)) {
                return $rows;
            }
        }
        return [];
    }

    /**
     * @param array<mixed> $bindings
     * @return array<string, mixed>|false
     */
    public function fetchRow(string $sql, array $bindings = []): array|false
    {
        $this->calls[] = ['method' => 'fetchRow', 'sql' => $sql, 'bindings' => $bindings];
        foreach ($this->responses as $fragment => $rows) {
            if (str_contains($sql, $fragment)) {
                return $rows[0] ?? false;
            }
        }
        return false;
    }

    public function quote(string $value): string
    {
        return "'" . addslashes($value) . "'";
    }
}
