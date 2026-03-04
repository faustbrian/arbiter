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
 * Exception thrown when attempting to list accessible paths without setting a capability.
 *
 * This exception enforces the requirement that a capability must be specified
 * via can() before calling listAccessiblePaths(), as the system needs to know
 * which capability to check against when determining path accessibility.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CapabilityMustBeSetException extends LogicException implements ArbiterException
{
    /**
     * Create an exception for when paths are listed without setting a capability first.
     *
     * @return self The exception instance with an appropriate error message
     */
    public static function beforeListingPaths(): self
    {
        return new self('Capability must be set before listing accessible paths. Use can($path, $capability) first.');
    }
}
