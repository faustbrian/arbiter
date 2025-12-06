<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Condition;

/**
 * Contract for condition implementations that evaluate values against rules.
 *
 * Defines the interface for Strategy pattern implementations that determine
 * whether a given value satisfies specific criteria. Used by the policy system
 * to validate context values against condition definitions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ConditionInterface
{
    /**
     * Evaluate the condition against a given value.
     *
     * Determines whether the provided value satisfies the condition's criteria.
     * Implementations should return true when the value meets the condition
     * requirements and false otherwise.
     *
     * @param mixed $value The value to evaluate against the condition's logic
     *
     * @return bool True if the condition is met, false otherwise
     */
    public function evaluate(mixed $value): bool;
}
