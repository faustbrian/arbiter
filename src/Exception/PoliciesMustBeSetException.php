<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

use LogicException;

/**
 * Exception thrown when attempting evaluation without setting policies.
 *
 * This exception enforces the requirement that policies must be loaded and
 * configured via against() before calling evaluation methods. The evaluator
 * needs policy definitions to determine what access rules to check against
 * when validating path and capability combinations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PoliciesMustBeSetException extends LogicException implements ArbiterException
{
    /**
     * Create an exception for when evaluation is attempted without setting policies.
     *
     * @return self The exception instance with instructions for proper policy configuration
     */
    public static function beforeEvaluation(): self
    {
        return new self('Policies must be set via against() before evaluation.');
    }
}
