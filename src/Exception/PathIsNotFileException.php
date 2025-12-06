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
 * Exception thrown when a path exists but is not a regular file.
 *
 * This exception occurs when attempting operations that require a regular file
 * but the path points to a directory, symbolic link, or other non-file type.
 * Used during file validation to ensure policy files and configuration files
 * are actual files rather than directories or special filesystem entries.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PathIsNotFileException extends RuntimeException implements ArbiterException
{
    /**
     * Create an exception for a path that exists but is not a regular file.
     *
     * @param  string $path Absolute or relative path that points to a non-file entry (directory, symlink, etc.)
     * @return self   Exception instance with contextual error message including the path
     */
    public static function atPath(string $path): self
    {
        return new self('Path is not a file: '.$path);
    }
}
