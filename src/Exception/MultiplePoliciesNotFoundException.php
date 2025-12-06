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
 * Exception thrown when multiple requested policies cannot be located.
 *
 * Indicates that the policy system attempted to load or retrieve multiple
 * policies by name, but one or more of the requested policies do not exist
 * in the policy registry. This is typically thrown during bulk policy
 * operations or when resolving policy dependencies.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MultiplePoliciesNotFoundException extends RuntimeException implements ArbiterException
{
    /**
     * Creates an exception for multiple missing policies identified by name.
     *
     * @param  string $nameList Comma-separated or formatted list of policy names that could not be found
     * @return self   Exception instance with contextual error message listing the missing policy names
     */
    public static function forNames(string $nameList): self
    {
        return new self('Policies not found: '.$nameList);
    }
}
