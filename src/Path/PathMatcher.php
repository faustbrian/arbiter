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
 * Provides flexible path matching for access control rules supporting exact paths,
 * single-segment wildcards, multi-segment glob patterns, and dynamic variable
 * substitution. All paths are normalized before comparison to ensure consistent
 * matching behavior.
 *
 * Pattern types:
 * - Exact: /foo/bar matches only /foo/bar
 * - Single wildcard: /foo/* matches /foo/bar but not /foo/bar/baz
 * - Glob wildcard: /foo/** matches /foo/bar and /foo/bar/baz
 * - Variables: /foo/${id}/bar with context ['id' => 'xyz'] matches /foo/xyz/bar
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class PathMatcher
{
    /**
     * Create a new path matcher instance.
     *
     * @param null|PathNormalizer   $normalizer       Optional path normalizer for consistent formatting.
     *                                                Defaults to a new PathNormalizer instance.
     * @param null|VariableResolver $variableResolver Optional variable resolver for ${var} substitution.
     *                                                Defaults to a new VariableResolver instance.
     */
    public function __construct(
        private ?PathNormalizer $normalizer = new PathNormalizer(),
        private ?VariableResolver $variableResolver = new VariableResolver(
        ),
    ) {}

    /**
     * Check if a path matches a pattern.
     *
     * Normalizes both the pattern and path, resolves any variables in the pattern
     * using the provided context, converts the pattern to a regular expression,
     * and performs the match. Returns true if the path matches the pattern.
     *
     * Pattern examples:
     * - Exact: /foo/bar matches only /foo/bar
     * - Single wildcard: /foo/* matches /foo/bar but not /foo/bar/baz
     * - Glob wildcard: /foo/** matches /foo/bar and /foo/bar/baz
     * - Variables: /foo/${id}/bar with context ['id' => 'xyz'] matches /foo/xyz/bar
     *
     * @param  string               $pattern The pattern to match against (may contain wildcards and variables)
     * @param  string               $path    The actual path to test for matching
     * @param  array<string, mixed> $context Variable substitution context for resolving ${var} placeholders
     * @return bool                 True if the path matches the pattern, false otherwise
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
     * Normalize a path using the configured normalizer.
     *
     * Delegates to PathNormalizer to ensure consistent path formatting
     * with leading slash, no trailing slash (except root), and collapsed
     * multiple consecutive slashes.
     *
     * @param  string $path The path to normalize
     * @return string The normalized path with consistent formatting
     */
    public function normalize(string $path): string
    {
        assert($this->normalizer instanceof PathNormalizer);

        return $this->normalizer->normalize($path);
    }

    /**
     * Resolve variables in a pattern using the provided context.
     *
     * Filters the context to only string and numeric values, converts numeric
     * values to strings, and delegates to VariableResolver to substitute
     * ${variable_name} placeholders with their corresponding values.
     *
     * @param  string               $pattern The pattern containing ${var} placeholders
     * @param  array<string, mixed> $context Key-value pairs for variable substitution
     * @return string               The pattern with all resolvable variables replaced
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
     * Extract variable values from a path given a pattern template.
     *
     * Reverses the variable substitution process by identifying ${variable_name}
     * placeholders in the pattern and extracting their corresponding values from
     * the actual path. Both pattern and path are normalized before extraction.
     *
     * Example:
     * ```php
     * $matcher = new PathMatcher();
     * $vars = $matcher->extractVariables(
     *     '/customers/${customer_id}/settings',
     *     '/customers/cust-123/settings'
     * );
     * // Returns: ['customer_id' => 'cust-123']
     * ```
     *
     * @param  string                $pattern The pattern template containing ${var} placeholders
     * @param  string                $path    The actual path to extract variable values from
     * @return array<string, string> Associative array of variable names to their extracted values
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
     * Convert a path pattern to a regular expression for matching.
     *
     * Escapes special regex characters while preserving pattern semantics,
     * then converts wildcard and glob patterns to their regex equivalents:
     * - ** (glob) matches zero or more path segments
     * - * (wildcard) matches exactly one path segment
     * - Variables are expected to be resolved before calling this method
     *
     * The conversion handles edge cases like zero-segment matching for globs
     * (/foo/** matches /foo) and proper path boundary detection.
     *
     * @param  string $pattern The normalized pattern to convert (variables should already be resolved)
     * @return string The complete regex pattern with anchors for full-path matching
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
