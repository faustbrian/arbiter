<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Condition;

/**
 * Condition that checks for strict equality between values.
 *
 * Uses PHP's strict comparison operator (===) to ensure both value and type
 * match the expected value. This prevents type coercion issues and ensures
 * precise matching for policy condition evaluation.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class EqualsCondition implements ConditionInterface
{
    /**
     * Create a new equals condition.
     *
     * @param mixed $expected The expected value to compare against using strict equality.
     *                        Can be any type including null, scalars, arrays, or objects.
     *                        The comparison will use === to ensure type-safe matching.
     */
    public function __construct(
        private mixed $expected,
    ) {}

    /**
     * Evaluate if the given value equals the expected value.
     *
     * Performs strict equality comparison (===) to ensure both value and type
     * match. This prevents "1" == 1 type coercion scenarios and ensures precise
     * condition matching.
     *
     * @param mixed $value The value to evaluate against the expected value
     *
     * @return bool True if the value strictly equals the expected value, false otherwise
     */
    public function evaluate(mixed $value): bool
    {
        return $value === $this->expected;
    }
}
