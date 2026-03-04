<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

/**
 * Exception thrown when a policy rule is not formatted as an array.
 *
 * Policy rules must be structured as arrays containing the rule configuration.
 * This exception is raised during policy validation when a rule is encountered
 * that is not in the expected array format, preventing proper rule evaluation.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class EachPolicyRuleMustBeArrayException extends InvalidPolicyDataException
{
    /**
     * Create an exception for invalid rule format.
     *
     * @return self The exception instance indicating rule must be an array
     */
    public static function create(): self
    {
        return new self('Each rule must be an array');
    }
}
