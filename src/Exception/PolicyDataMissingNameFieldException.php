<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

/**
 * Exception thrown when policy data lacks the required name field.
 *
 * Every policy definition must include a "name" field containing a non-empty
 * string that uniquely identifies the policy. This field is essential for
 * policy lookup, reference, and debugging. Thrown during policy validation
 * when parsing policy files that omit this required field.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PolicyDataMissingNameFieldException extends InvalidPolicyDataException
{
    /**
     * Create an exception for policy data missing the required name field.
     *
     * @return self The exception instance with schema validation error details
     */
    public static function create(): self
    {
        return new self('Policy data must contain a "name" field of type string');
    }
}
