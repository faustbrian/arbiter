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
 * Exception thrown when a required JSON file does not exist.
 *
 * Indicates that the policy system attempted to load a JSON file at a
 * specified path, but the file does not exist at that location. This
 * typically occurs during policy loading when configuration points to
 * a non-existent file or when files have been moved or deleted.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class JsonFileNotFoundException extends RuntimeException implements ArbiterException
{
    /**
     * Creates an exception for a missing JSON file at a specific path.
     *
     * @param  string $path Absolute or relative file path where the JSON file was expected but not found
     * @return self   Exception instance with contextual error message including the file path
     */
    public static function atPath(string $path): self
    {
        return new self('JSON file not found: '.$path);
    }
}
