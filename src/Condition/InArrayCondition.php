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
 * Condition that checks if a value is in an array of allowed values.
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class InArrayCondition implements ConditionInterface
{
    /**
     * @param array<mixed> $allowedValues The array of allowed values
     */
    public function __construct(
        private array $allowedValues,
    ) {}

    /**
     * Evaluate if the given value is in the array of allowed values.
     *
     * @param  mixed $value The value to evaluate
     * @return bool  True if the value is in the allowed values array, false otherwise
     */
    public function evaluate(mixed $value): bool
    {
        return in_array($value, $this->allowedValues, true);
    }
}
