<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Path;

use function array_filter;
use function array_map;
use function assert;
use function is_numeric;
use function is_string;
use function preg_match;
use function preg_quote;
use function str_replace;

/**
 * Matches paths against patterns with wildcard and variable support.
 *
 * Supports:
 * - Exact matching: /foo/bar matches only /foo/bar
 * - Single wildcard: /foo/* matches /foo/bar but not /foo/bar/baz
 * - Glob wildcard: /foo/** matches /foo/bar and /foo/bar/baz
 * - Variables: /foo/${id}/bar with context ['id' => 'xyz'] matches /foo/xyz/bar
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class PathMatcher
{
    public function __construct(
        private ?PathNormalizer $normalizer = new PathNormalizer(),
        private ?VariableResolver $variableResolver = new VariableResolver(
        ),
    ) {}

    /**
     * Check if a path matches a pattern.
     *
     * Patterns:
     * - Exact: /foo/bar matches only /foo/bar
     * - Single wildcard: /foo/* matches /foo/bar but not /foo/bar/baz
     * - Glob wildcard: /foo/** matches /foo/bar and /foo/bar/baz
     * - Variables: /foo/${id}/bar with context ['id' => 'xyz'] matches /foo/xyz/bar
     *
     * @param  string               $pattern The pattern to match against
     * @param  string               $path    The path to test
     * @param  array<string, mixed> $context Variables for substitution
     * @return bool                 True if the path matches the pattern
     */
    public function matches(string $pattern, string $path, array $context = []): bool
    {
        // Normalize both pattern and path
        $pattern = $this->normalize($pattern);
        $path = $this->normalize($path);

        // Resolve variables in the pattern
        $pattern = $this->resolveVariables($pattern, $context);

        // Convert pattern to regex
        $regex = $this->patternToRegex($pattern);

        return preg_match($regex, $path) === 1;
    }

    /**
     * Normalize a path (remove trailing slashes, etc.).
     *
     * @param  string $path The path to normalize
     * @return string The normalized path
     */
    public function normalize(string $path): string
    {
        assert($this->normalizer instanceof PathNormalizer);

        return $this->normalizer->normalize($path);
    }

    /**
     * Resolve variables in pattern.
     *
     * @param  string               $pattern The pattern containing variables
     * @param  array<string, mixed> $context Variables for substitution
     * @return string               The pattern with variables resolved
     */
    public function resolveVariables(string $pattern, array $context): string
    {
        // Filter context to only string values for variable resolution
        $stringContext = array_filter(
            $context,
            fn ($value): bool => is_string($value) || is_numeric($value),
        );

        // Convert numeric values to strings
        $stringContext = array_map(
            fn (float|int|string $value): string => (string) $value,
            $stringContext,
        );

        assert($this->variableResolver instanceof VariableResolver);

        return $this->variableResolver->resolve($pattern, $stringContext);
    }

    /**
     * Extract variable values from a path given a pattern.
     *
     * Pattern: /customers/${customer_id}/settings
     * Path: /customers/cust-123/settings
     * Returns: ['customer_id' => 'cust-123']
     *
     * @param  string                $pattern The pattern containing variables
     * @param  string                $path    The path to extract from
     * @return array<string, string> Extracted variable names and values
     */
    public function extractVariables(string $pattern, string $path): array
    {
        // Normalize both pattern and path
        $pattern = $this->normalize($pattern);
        $path = $this->normalize($path);

        assert($this->variableResolver instanceof VariableResolver);

        return $this->variableResolver->extract($pattern, $path);
    }

    /**
     * Convert a pattern to a regular expression.
     *
     * @param  string $pattern The pattern to convert
     * @return string The regex pattern
     */
    private function patternToRegex(string $pattern): string
    {
        // Escape special regex characters except * and ${}
        $regex = preg_quote($pattern, '#');

        // Replace escaped wildcards with their regex equivalents
        // ** matches any number of path segments (including zero)
        // We need to handle ** carefully to allow zero segments
        // Note: preg_quote escapes * to \* but NOT /

        // Replace **/ (at start or after /) with optional path segments
        // This allows /foo/**/bar to match /foo/bar (zero segments)
        $regex = str_replace('\\*\\*/', '(?:(?:[^/]+/)*)?', $regex);

        // Replace /** at the end with optional slash and anything after
        // This allows /foo/** to match /foo (zero segments)
        $regex = str_replace('/\\*\\*', '(?:/.*)?', $regex);

        // Replace any remaining ** (standalone) with zero or more of anything
        $regex = str_replace('\\*\\*', '.*', $regex);

        // * matches a single path segment (no slashes)
        $regex = str_replace('\\*', '[^/]+', $regex);

        // Note: We do NOT convert ${var} patterns here because variables
        // are resolved BEFORE this method is called. Any remaining ${var}
        // patterns should be treated as literal text.

        return '#^'.$regex.'$#';
    }
}
