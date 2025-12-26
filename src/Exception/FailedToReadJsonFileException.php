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
 * Exception thrown when a JSON file cannot be read or parsed.
 *
 * This exception occurs when attempting to load policy data from a JSON file
 * but the operation fails. Failure can be due to file read errors, permission
 * issues, or invalid JSON syntax that cannot be parsed.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FailedToReadJsonFileException extends RuntimeException implements ArbiterException
{
    /**
     * Create an exception for a JSON file that could not be read or parsed.
     *
     * @param  string $path The path to the JSON file that failed to be processed
     * @return self   The exception instance with the file path in the message
     */
    public static function atPath(string $path): self
    {
        return new self('Failed to read JSON file: '.$path);
    }
}
