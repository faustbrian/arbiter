<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Path;

use RuntimeException;

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
 * Handles variable substitution using ${variable_name} syntax and can extract
 * variable values from paths given a pattern template.
 * @author Brian Faust <brian@cline.sh>
 */
final class VariableResolver
{
    /**
     * Resolve variables in a pattern using the provided context.
     *
     * Replaces ${variable_name} placeholders with values from the context array.
     *
     * Example:
     *   Pattern: /customers/${customer_id}/settings
     *   Context: ['customer_id' => 'cust-123']
     *   Result:  /customers/cust-123/settings
     *
     * @param  string                $pattern The pattern containing variables
     * @param  array<string, string> $context Key-value pairs for variable substitution
     * @return string                The pattern with variables resolved
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
        throw_if($result === null, RuntimeException::class, 'Failed to resolve variables: preg_replace_callback returned null');

        return $result;
    }

    /**
     * Extract variable values from a path given a pattern.
     *
     * Identifies variable placeholders in the pattern and extracts their
     * corresponding values from the actual path.
     *
     * Example:
     *   Pattern: /customers/${customer_id}/settings
     *   Path:    /customers/cust-123/settings
     *   Result:  ['customer_id' => 'cust-123']
     *
     * @param  string                $pattern The pattern containing variables
     * @param  string                $path    The actual path to extract from
     * @return array<string, string> Extracted variable names and values
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
