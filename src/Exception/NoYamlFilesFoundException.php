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
 * Exception thrown when no YAML files are found in a directory.
 * @author Brian Faust <brian@cline.sh>
 */
final class NoYamlFilesFoundException extends RuntimeException implements ArbiterException
{
    public static function inDirectory(string $path): self
    {
        return new self('No YAML files found in directory: '.$path);
    }
}
