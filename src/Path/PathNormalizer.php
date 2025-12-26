<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Path;

use Cline\Arbiter\Exception\FailedToNormalizePathException;

use function mb_rtrim;
use function preg_replace;
use function str_ends_with;
use function str_starts_with;
use function throw_if;

/**
 * Normalizes file system paths for consistent comparison and matching.
 *
 * Ensures all paths follow a standard format: leading slash, no trailing slash
 * (except for root "/"), and collapsed consecutive slashes. This normalization
 * is essential for reliable path matching in access control rules.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PathNormalizer
{
    /**
     * Normalize a path to a consistent canonical format.
     *
     * Applies the following transformations to ensure consistent path representation:
     * - Ensures leading slash (converts "" to "/", "foo" to "/foo")
     * - Removes trailing slash except for root path (converts "/foo/" to "/foo")
     * - Collapses multiple consecutive slashes into one (converts "//foo///bar" to "/foo/bar")
     *
     * Empty string input is normalized to root path "/".
     *
     * @param string $path The path to normalize (may be empty, relative, or absolute)
     *
     * @throws FailedToNormalizePathException If regex replacement fails
     * @return string                         The normalized absolute path with consistent formatting
     */
    public function normalize(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        // Collapse multiple consecutive slashes into one
        $normalized = preg_replace('#/+#', '/', $path);

        // preg_replace can return null on error, assert it returns string
        throw_if($normalized === null, FailedToNormalizePathException::pregReplaceReturnedNull());

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
