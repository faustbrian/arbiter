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
 * Exception thrown when JSON content is invalid.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidJsonException extends RuntimeException implements ArbiterException
{
    public static function inFile(string $path): self
    {
        return new self('Invalid JSON in file: '.$path);
    }
}
