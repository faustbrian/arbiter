<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

/**
 * Exception thrown when the policy rules field has an invalid type.
 *
 * The "rules" field in a policy definition must be an array containing one or
 * more rule objects. This exception occurs when the field exists but contains
 * a non-array value such as a string, number, or object. Proper policy structure
 * requires rules to be an array for iteration and validation during policy loading.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PolicyRulesMustBeArrayException extends InvalidPolicyDataException
{
    /**
     * Create an exception for a policy with an invalid rules field type.
     *
     * @return self The exception instance with type validation error details
     */
    public static function create(): self
    {
        return new self('Policy "rules" must be an array');
    }
}
