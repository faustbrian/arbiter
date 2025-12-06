<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Condition;

/**
 * Condition that checks if a value equals an expected value.
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class EqualsCondition implements ConditionInterface
{
    /**
     * @param mixed $expected The expected value to compare against
     */
    public function __construct(
        private mixed $expected,
    ) {}

    /**
     * Evaluate if the given value equals the expected value.
     *
     * @param  mixed $value The value to evaluate
     * @return bool  True if the value equals the expected value, false otherwise
     */
    public function evaluate(mixed $value): bool
    {
        return $value === $this->expected;
    }
}
