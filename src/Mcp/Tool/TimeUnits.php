<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

/**
 * Converts relative time specification strings (e.g. '5m', '2h', '7d') to seconds,
 * cutoff timestamps, and hour values.
 */
final class TimeUnits
{
    private const PATTERN = '/^(\d+)([mhd])$/';

    /**
     * Convert a relative time spec to seconds.
     *
     * @param string $since Relative time ('5m', '1h', '24h', '7d')
     * @return int|null Seconds, or null if the format is invalid
     */
    public static function toSeconds(string $since): ?int
    {
        if (!preg_match(self::PATTERN, $since, $m)) {
            return null;
        }

        $value = (int) $m[1];
        return match ($m[2]) {
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
            default => 3600, // @phpstan-ignore-line
        };
    }

    /**
     * Convert a relative time spec to a Unix timestamp cutoff (time() - seconds).
     *
     * @param string $since Relative time ('5m', '1h', '24h', '7d')
     * @return int|null Cutoff timestamp, or null if format is invalid
     */
    public static function toCutoff(string $since): ?int
    {
        $seconds = self::toSeconds($since);
        return $seconds === null ? null : time() - $seconds;
    }

    /**
     * Convert a relative time spec to integer hours (minimum 1 on success).
     *
     * @param string $since Relative time ('5m', '1h', '24h', '7d')
     * @return int|null Hours (>= 1), or null if format is invalid
     */
    public static function toHours(string $since): ?int
    {
        $seconds = self::toSeconds($since);
        return $seconds === null ? null : max(1, (int) ceil($seconds / 3600));
    }
}
