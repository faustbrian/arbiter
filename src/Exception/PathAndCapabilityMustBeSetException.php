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
 * Exception thrown when attempting evaluation without setting path and capability.
 *
 * This exception enforces the requirement that both path and capability must be
 * specified via can($path, $capability) before calling evaluation methods like
 * passes() or fails(). The evaluator needs both pieces of information to properly
 * check access permissions against policy rules.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PathAndCapabilityMustBeSetException extends LogicException implements ArbiterException
{
    /**
     * Create an exception for when evaluation is attempted without setting path and capability.
     *
     * @return self The exception instance with instructions for proper usage
     */
    public static function beforeEvaluation(): self
    {
        return new self('Path and capability must be set before evaluation. Use can($path, $capability) first.');
    }
}
