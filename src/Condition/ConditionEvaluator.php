<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Condition;

use function is_array;
use function is_callable;

/**
 * Evaluates multiple conditions against a context.
 * @author Brian Faust <brian@cline.sh>
 */
final class ConditionEvaluator
{
    /**
     * Evaluate all conditions against the provided context.
     *
     * All conditions must be met for this method to return true.
     *
     * @param  array<string, mixed> $conditions Array of condition definitions keyed by context field name
     * @param  array<string, mixed> $context    The context data to evaluate against
     * @return bool                 True if all conditions are met, false otherwise
     */
    public function evaluateAll(array $conditions, array $context): bool
    {
        foreach ($conditions as $field => $definition) {
            $condition = $this->createCondition($definition);
            $value = $context[$field] ?? null;

            if (!$condition->evaluate($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create a Condition instance from a definition.
     *
     * Supports:
     * - string/int: Creates EqualsCondition
     * - array: Creates InArrayCondition
     * - callable: Creates CallableCondition
     *
     * @param  mixed              $definition The condition definition
     * @return ConditionInterface The created condition
     */
    private function createCondition(mixed $definition): ConditionInterface
    {
        if (is_callable($definition)) {
            return new CallableCondition($definition);
        }

        if (is_array($definition)) {
            return new InArrayCondition($definition);
        }

        return new EqualsCondition($definition);
    }
}
