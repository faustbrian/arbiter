<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

/**
 * Exception thrown when policy rules field is not an array.
 * @author Brian Faust <brian@cline.sh>
 */
final class PolicyRulesMustBeArrayException extends InvalidPolicyDataException
{
    public static function create(): self
    {
        return new self('Policy "rules" must be an array');
    }
}
