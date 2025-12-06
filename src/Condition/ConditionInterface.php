<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Condition;

/**
 * Represents a condition that can be evaluated against a value.
 * @author Brian Faust <brian@cline.sh>
 */
interface ConditionInterface
{
    /**
     * Evaluate the condition against a given value.
     *
     * @param  mixed $value The value to evaluate
     * @return bool  True if the condition is met, false otherwise
     */
    public function evaluate(mixed $value): bool;
}
