<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

use RuntimeException;

/**
 * Exception thrown when attempting to retrieve a non-existent policy by name.
 *
 * This exception occurs when code requests a specific policy by its name identifier
 * but no policy with that name exists in the loaded policy collection. Common causes
 * include typos in policy names, policies not being loaded from their source files,
 * or references to policies that have been removed or renamed.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PolicyNotFoundException extends RuntimeException implements ArbiterException
{
    /**
     * Create an exception for a policy that could not be found by its name.
     *
     * @param  string $name The policy name identifier that was requested but does not exist
     * @return self   Exception instance with contextual error message including the policy name
     */
    public static function forName(string $name): self
    {
        return new self('Policy not found: '.$name);
    }
}
