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
 * Exception thrown when JSON content fails parsing or validation.
 *
 * Indicates that a JSON file contains syntactically invalid JSON that cannot
 * be decoded. This typically occurs due to malformed JSON syntax, encoding
 * issues, or corrupted file content.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidJsonException extends RuntimeException implements ArbiterException
{
    /**
     * Creates an exception for invalid JSON content in a specific file.
     *
     * @param  string $path Absolute or relative file path to the JSON file that contains invalid content
     * @return self   Exception instance with contextual error message including the file path
     */
    public static function inFile(string $path): self
    {
        return new self('Invalid JSON in file: '.$path);
    }
}
