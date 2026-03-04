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
 * Exception thrown when a directory cannot be read due to filesystem errors.
 *
 * This exception occurs when attempting to read directory contents but the
 * operation fails, typically due to permission issues, I/O errors, or the
 * path existing but not being readable by the current process.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FailedToReadDirectoryException extends RuntimeException implements ArbiterException
{
    /**
     * Create an exception for a directory that could not be read.
     *
     * @param  string $path The path to the directory that failed to be read
     * @return self   The exception instance with the path included in the message
     */
    public static function atPath(string $path): self
    {
        return new self('Failed to read directory: '.$path);
    }
}
