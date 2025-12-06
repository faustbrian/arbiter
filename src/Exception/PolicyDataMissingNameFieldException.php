<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

/**
 * Exception thrown when policy data is missing the name field.
 * @author Brian Faust <brian@cline.sh>
 */
final class PolicyDataMissingNameFieldException extends InvalidPolicyDataException
{
    public static function create(): self
    {
        return new self('Policy data must contain a "name" field of type string');
    }
}
