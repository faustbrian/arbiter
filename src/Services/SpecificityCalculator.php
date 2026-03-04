<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Services;

use function count;
use function explode;
use function mb_trim;
use function str_contains;

/**
 * Calculates specificity scores for path patterns to determine rule precedence.
 *
 * Specificity helps resolve conflicts when multiple rules match the same path.
 * More specific patterns receive higher scores and take precedence during evaluation.
 *
 * Scoring logic:
 * - Glob wildcards (**) = lowest specificity (score: 1)
 * - More path segments = higher specificity
 * - Fewer wildcards/variables = higher specificity
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SpecificityCalculator
{
    /**
     * Calculate specificity score for a path pattern.
     *
     * Patterns with glob wildcards receive the lowest score.
     * For other patterns, specificity is calculated as segments minus wildcards.
     *
     * @param  string $pattern The path pattern to score
     * @return int    Specificity score (higher = more specific)
     */
    public function calculate(string $pattern): int
    {
        // Glob wildcard gets lowest priority
        if (str_contains($pattern, '**')) {
            return 1;
        }

        // Count path segments and wildcards
        $segments = explode('/', mb_trim($pattern, '/'));
        $wildcards = 0;

        foreach ($segments as $segment) {
            if ($segment !== '*' && !str_contains($segment, '${')) {
                continue;
            }

            ++$wildcards;
        }

        // More segments = more specific
        // Fewer wildcards = more specific
        return count($segments) - $wildcards;
    }
}
