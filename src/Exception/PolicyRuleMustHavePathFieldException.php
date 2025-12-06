<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

/**
 * Exception thrown when policy rule is missing the path field.
 * @author Brian Faust <brian@cline.sh>
 */
final class PolicyRuleMustHavePathFieldException extends InvalidPolicyDataException
{
    public static function create(): self
    {
        return new self('Each rule must have a "path" field of type string');
    }
}
