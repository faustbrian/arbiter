<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Condition;

/**
 * Condition that evaluates using a callable or closure.
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class CallableCondition implements ConditionInterface
{
    /**
     * @param callable $callable The callable to use for evaluation
     */
    public function __construct(
        private mixed $callable,
    ) {}

    /**
     * Evaluate the value using the provided callable.
     *
     * @param  mixed $value The value to evaluate
     * @return bool  True if the callable returns a truthy value, false otherwise
     */
    public function evaluate(mixed $value): bool
    {
        return (bool) ($this->callable)($value);
    }
}
