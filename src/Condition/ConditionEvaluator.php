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
 * Evaluates multiple conditions against a context using the Strategy pattern.
 *
 * Automatically creates appropriate condition instances based on definition types
 * (callable, array, or scalar) and evaluates them against context values. All
 * conditions must be satisfied for the evaluation to succeed, implementing AND logic.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConditionEvaluator
{
    /**
     * Evaluate all conditions against the provided context.
     *
     * All conditions must be met for this method to return true, implementing
     * AND logic across multiple condition checks. Short-circuits on the first
     * failing condition for performance optimization.
     *
     * @param array<string, mixed> $conditions Array of condition definitions keyed by context field name
     *                                         that will be matched against corresponding context values
     * @param array<string, mixed> $context    Context data containing values to evaluate against conditions
     *
     * @return bool True if all conditions are met, false otherwise
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
     * Create a Condition instance from a definition using factory pattern.
     *
     * Automatically selects the appropriate condition type based on the definition:
     * - Callable: Creates CallableCondition for complex custom logic
     * - Array: Creates InArrayCondition for membership checks
     * - Scalar: Creates EqualsCondition for exact value matching
     *
     * @param mixed $definition The condition definition value that determines which condition type to create
     *
     * @return ConditionInterface The created condition instance ready for evaluation
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
