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
 * Exception thrown when a YAML policy file cannot be found at the specified path.
 *
 * Indicates the file does not exist or is not accessible. Verify the path
 * and file permissions before attempting to load YAML policies.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class YamlFileNotFoundException extends RuntimeException implements ArbiterException
{
    /**
     * Create a new exception for the given file path.
     *
     * @param string $path The path to the missing YAML file
     */
    public static function atPath(string $path): self
    {
        return new self('YAML file not found: '.$path);
    }
}
