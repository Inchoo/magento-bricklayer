<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Concern;

/**
 * Provides a single canonical sensitive-prefix list and path-masking helpers.
 *
 * Prefix semantics: bare prefixes with str_starts_with.
 *
 * Bare (not trailing-slash) is the correct form because:
 *  1. Section-root paths (e.g. "payment") must also be masked.
 *     str_starts_with('payment', 'payment/') === false — trailing-slash would miss them.
 *  2. Real elasticsearch field paths such as "catalog/search/elasticsearch7_server_hostname"
 *     must match the prefix "catalog/search/elasticsearch".
 *     A segment-aware rule ($path === $prefix || str_starts_with($path, $prefix.'/'))
 *     would NOT match "elasticsearch7..." because "7" does not follow a slash boundary.
 *     Bare str_starts_with handles both cases uniformly.
 */
trait MasksSensitiveConfig
{
    /**
     * Return the canonical list of sensitive config-path prefixes.
     *
     * Both DatabaseTools and ConfigurationTools derive their prefix list from
     * this single method — the one source of truth that eliminates drift.
     *
     * @return list<string>
     */
    private function sensitivePrefixes(): array
    {
        return [
            'payment',
            'carriers',
            'system/smtp',
            'trans_email',
            'oauth',
            'admin/security',
            'catalog/search/elasticsearch',
            'catalog/search/opensearch',
        ];
    }

    /**
     * Determine whether a config path falls under a sensitive prefix.
     */
    private function isSensitivePath(string $path): bool
    {
        foreach ($this->sensitivePrefixes() as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return the standard mask string used wherever a sensitive value is hidden.
     */
    private function maskValue(): string
    {
        return '***MASKED***';
    }
}
