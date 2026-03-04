<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Condition;

use function in_array;

/**
 * Condition that checks if a value exists in an array of allowed values.
 *
 * Uses strict comparison (in_array with strict mode) to check for membership
 * in a whitelist of acceptable values. Useful for validating enum-like values,
 * allowed statuses, or permitted identifiers in policy conditions.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class InArrayCondition implements ConditionInterface
{
    /**
     * Create a new in-array condition.
     *
     * @param array<mixed> $allowedValues Whitelist of acceptable values that will satisfy this condition.
     *                                    The evaluation uses strict comparison to prevent type coercion issues.
     *                                    Common use cases include role lists, status enums, or ID collections.
     */
    public function __construct(
        private array $allowedValues,
    ) {}

    /**
     * Evaluate if the given value is in the array of allowed values.
     *
     * Performs strict membership check using in_array with strict mode enabled,
     * ensuring both value and type must match. This prevents issues like
     * "1" matching 1 in the allowed values array.
     *
     * @param mixed $value The value to check against the whitelist of allowed values
     *
     * @return bool True if the value is in the allowed values array, false otherwise
     */
    public function evaluate(mixed $value): bool
    {
        return in_array($value, $this->allowedValues, true);
    }
}
