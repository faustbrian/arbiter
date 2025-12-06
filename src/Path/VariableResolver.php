<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Path;

use Cline\Arbiter\Exception\FailedToResolveVariablesException;

use const ARRAY_FILTER_USE_KEY;

use function array_filter;
use function is_numeric;
use function preg_match;
use function preg_quote;
use function preg_replace_callback;
use function throw_if;

/**
 * Resolves and extracts variables in path patterns.
 *
 * Provides bidirectional variable handling: substitutes ${variable_name} placeholders
 * with actual values for pattern matching, and extracts variable values from paths
 * given a pattern template. Supports standard identifier naming (alphanumeric and underscore).
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class VariableResolver
{
    /**
     * Resolve variables in a pattern using the provided context.
     *
     * Replaces ${variable_name} placeholders with their corresponding values
     * from the context array. Variables follow standard identifier naming rules
     * (letters, numbers, underscore; must start with letter or underscore).
     * Unmatched variables are left as-is in the pattern.
     *
     * Example:
     * ```php
     * $resolver = new VariableResolver();
     * $result = $resolver->resolve(
     *     '/customers/${customer_id}/settings',
     *     ['customer_id' => 'cust-123']
     * );
     * // Returns: /customers/cust-123/settings
     * ```
     *
     * @param string                $pattern The pattern containing ${variable_name} placeholders
     * @param array<string, string> $context Associative array of variable names to replacement values
     *
     * @throws FailedToResolveVariablesException If regex replacement operation fails
     * @return string                            The pattern with all matched variables replaced by their values
     */
    public function resolve(string $pattern, array $context): string
    {
        $result = preg_replace_callback(
            '/\$\{([a-zA-Z_]\w*)\}/',
            function (array $matches) use ($context): string {
                $variableName = $matches[1];

                return $context[$variableName] ?? $matches[0];
            },
            $pattern,
        );

        // preg_replace_callback can return null on error, assert it returns string
        throw_if($result === null, FailedToResolveVariablesException::pregReplaceCallbackReturnedNull());

        return $result;
    }

    /**
     * Extract variable values from a path given a pattern template.
     *
     * Reverses the resolution process by converting the pattern into a regular
     * expression with named capture groups, then matching against the path to
     * extract variable values. Each ${variable_name} becomes a capture group
     * that matches any non-slash characters.
     *
     * Returns empty array if the path doesn't match the pattern structure.
     *
     * Example:
     * ```php
     * $resolver = new VariableResolver();
     * $vars = $resolver->extract(
     *     '/customers/${customer_id}/settings',
     *     '/customers/cust-123/settings'
     * );
     * // Returns: ['customer_id' => 'cust-123']
     * ```
     *
     * @param  string                $pattern The pattern template containing ${variable_name} placeholders
     * @param  string                $path    The actual path to extract variable values from
     * @return array<string, string> Associative array of variable names to extracted values,
     *                               or empty array if path doesn't match pattern
     */
    public function extract(string $pattern, string $path): array
    {
        // Convert pattern to a regex, capturing variable positions
        // After preg_quote, ${var} becomes \$\{var\}, so we need to match that
        $regex = preg_replace_callback(
            '/\\\\\\$\\\\\\{([a-zA-Z_]\w*)\\\\\\}/',
            fn (array $matches): string => '(?P<'.$matches[1].'>[^/]+)',
            preg_quote($pattern, '#'),
        );

        $regex = '#^'.$regex.'$#';

        if (!preg_match($regex, $path, $matches)) {
            return [];
        }

        // Extract only named captures (filter out numeric indices)
        return array_filter(
            $matches,
            fn (string $key): bool => !is_numeric($key),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
