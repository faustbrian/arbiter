<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Path;

use RuntimeException;

use function mb_rtrim;
use function preg_replace;
use function str_ends_with;
use function str_starts_with;
use function throw_if;

/**
 * Normalizes file system paths for consistent comparison and matching.
 *
 * Ensures paths have a leading slash, no trailing slash (except for root),
 * and collapses multiple consecutive slashes into a single slash.
 * @author Brian Faust <brian@cline.sh>
 */
final class PathNormalizer
{
    /**
     * Normalize a path to a consistent format.
     *
     * - Ensures leading slash
     * - Removes trailing slash (except for root path "/")
     * - Collapses multiple consecutive slashes into one
     *
     * @param  string $path The path to normalize
     * @return string The normalized path
     */
    public function normalize(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        // Collapse multiple consecutive slashes into one
        $normalized = preg_replace('#/+#', '/', $path);

        // preg_replace can return null on error, assert it returns string
        throw_if($normalized === null, RuntimeException::class, 'Failed to normalize path: preg_replace returned null');

        // Ensure leading slash
        if (!str_starts_with($normalized, '/')) {
            $normalized = '/'.$normalized;
        }

        // Remove trailing slash unless it's the root path
        if ($normalized !== '/' && str_ends_with($normalized, '/')) {
            return mb_rtrim($normalized, '/');
        }

        return $normalized;
    }
}
