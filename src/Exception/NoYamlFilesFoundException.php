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
 * Exception thrown when a directory contains no YAML files.
 *
 * Indicates that the policy system attempted to scan a directory for YAML
 * policy files but found none matching the expected file patterns. This
 * typically occurs during policy discovery when a configured policy directory
 * is empty or contains only non-YAML files.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class NoYamlFilesFoundException extends RuntimeException implements ArbiterException
{
    /**
     * Creates an exception for a directory with no YAML files.
     *
     * @param  string $path Absolute or relative directory path that was scanned but contained no YAML files
     * @return self   Exception instance with contextual error message including the directory path
     */
    public static function inDirectory(string $path): self
    {
        return new self('No YAML files found in directory: '.$path);
    }
}
