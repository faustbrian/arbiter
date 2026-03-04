<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

/**
 * Exception thrown when a policy rule lacks the required path field.
 *
 * Every rule within a policy's "rules" array must contain a "path" field
 * that specifies which filesystem paths or path patterns the rule applies to.
 * This field is fundamental to the policy matching engine and cannot be omitted.
 * Thrown during policy validation when parsing rules that lack this required field.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PolicyRuleMustHavePathFieldException extends InvalidPolicyDataException
{
    /**
     * Create an exception for a policy rule missing the required path field.
     *
     * @return self The exception instance with schema validation error details
     */
    public static function create(): self
    {
        return new self('Each rule must have a "path" field of type string');
    }
}
