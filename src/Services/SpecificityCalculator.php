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
 * @author Brian Faust <brian@cline.sh>
 */
final class SpecificityCalculator
{
    /**
     * Calculate specificity score for a path pattern.
     * Higher score = more specific.
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
