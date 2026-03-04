<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception thrown when a policy definition has an invalid type.
 *
 * Policy definitions must be either a JSON string or an array structure.
 * This exception is thrown when a definition is provided in an unsupported
 * format, preventing proper policy configuration and initialization.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidDefinitionTypeException extends RuntimeException implements ArbiterException
{
    /**
     * Create exception for invalid policy definition type.
     *
     * @param string $name The policy name that has an invalid definition type
     *
     * @return self Exception instance with descriptive error message
     */
    public static function forPolicy(string $name): self
    {
        return new self(
            sprintf("Invalid definition for policy '%s': expected JSON string or array", $name),
        );
    }
}
