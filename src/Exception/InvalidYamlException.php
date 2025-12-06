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
 * Exception thrown when YAML content fails parsing or validation.
 *
 * Indicates that a YAML file contains syntactically invalid YAML that cannot
 * be parsed. This typically occurs due to malformed YAML syntax, indentation
 * errors, invalid anchor references, or corrupted file content.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidYamlException extends RuntimeException implements ArbiterException
{
    /**
     * Creates an exception for invalid YAML content in a specific file.
     *
     * @param  string $path Absolute or relative file path to the YAML file that contains invalid content
     * @return self   Exception instance with contextual error message including the file path
     */
    public static function inFile(string $path): self
    {
        return new self('Invalid YAML in file: '.$path);
    }
}
