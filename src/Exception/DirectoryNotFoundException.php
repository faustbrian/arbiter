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
 * Exception thrown when a required directory does not exist.
 *
 * This exception occurs when attempting to access or read a directory
 * that is not present on the filesystem, typically during policy loading
 * or path scanning operations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DirectoryNotFoundException extends RuntimeException implements ArbiterException
{
    /**
     * Create an exception for a directory that was not found at the given path.
     *
     * @param  string $path The path to the directory that does not exist
     * @return self   The exception instance with the path included in the message
     */
    public static function atPath(string $path): self
    {
        return new self('Directory not found: '.$path);
    }
}
