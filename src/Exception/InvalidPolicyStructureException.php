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
 * Exception thrown when a policy file contains structurally invalid data.
 *
 * Indicates that while the file format (JSON/YAML) may be valid, the policy
 * structure within does not conform to expected schema requirements. This
 * includes missing required top-level keys, incorrect nesting, or malformed
 * policy hierarchies that prevent proper policy parsing and loading.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidPolicyStructureException extends RuntimeException implements ArbiterException
{
    /**
     * Creates an exception for invalid policy structure in a specific file.
     *
     * @param  string $path Absolute or relative file path to the policy file with structural issues
     * @return self   Exception instance with contextual error message including the file path
     */
    public static function inFile(string $path): self
    {
        return new self('Invalid policy structure in file: '.$path);
    }
}
