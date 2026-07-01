<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralized metric math. All calculation lives in the backend
 * (CLAUDE.md Data & Metrics Rules).
 */
final class Metrics
{
    public static function ratio(float|int $num, float|int $den, int $precision = 2): float
    {
        if ($den == 0.0) {
            return 0.0;
        }

        return round(($num / $den) * 100, $precision);
    }

    public static function ecpm(float|int $revenue, float|int $impressions, int $precision = 2): float
    {
        if ($impressions == 0.0) {
            return 0.0;
        }

        return round(($revenue / $impressions) * 1000, $precision);
    }

    public static function div(float|int $num, float|int $den, int $precision = 2): float
    {
        if ($den == 0.0) {
            return 0.0;
        }

        return round($num / $den, $precision);
    }

    /** Percentage change of current vs previous (e.g. revenue trend). */
    public static function change(float|int $current, float|int $previous, int $precision = 1): float
    {
        if ($previous == 0.0) {
            return 0.0;
        }

        return round((($current - $previous) / $previous) * 100, $precision);
    }
}
