<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Condition;

/**
 * Condition that evaluates values using a custom callable or closure.
 *
 * Provides maximum flexibility for complex evaluation logic that cannot
 * be expressed through simple equality or array membership checks. The
 * callable receives the context value and should return a truthy result
 * to indicate the condition is met.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class CallableCondition implements ConditionInterface
{
    /**
     * Create a new callable condition.
     *
     * @param callable $callable Custom evaluation function that receives the context value
     *                           and returns a boolean or truthy result indicating whether
     *                           the condition is satisfied. Enables complex validation logic
     *                           such as date comparisons, pattern matching, or business rules.
     */
    public function __construct(
        private mixed $callable,
    ) {}

    /**
     * Evaluate the value using the provided callable.
     *
     * Invokes the callable with the provided value and coerces the result
     * to a boolean. This allows for flexible condition logic while maintaining
     * a consistent boolean return type.
     *
     * @param mixed $value The context value to evaluate against the callable logic
     *
     * @return bool True if the callable returns a truthy value, false otherwise
     */
    public function evaluate(mixed $value): bool
    {
        return (bool) ($this->callable)($value);
    }
}
