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
 * Exception thrown when policies must be set before evaluation.
 * @author Brian Faust <brian@cline.sh>
 */
final class PoliciesMustBeSetException extends LogicException implements ArbiterException
{
    public static function beforeEvaluation(): self
    {
        return new self('Policies must be set via against() before evaluation.');
    }
}
