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
 * Exception thrown when a path is not a file.
 * @author Brian Faust <brian@cline.sh>
 */
final class PathIsNotFileException extends RuntimeException implements ArbiterException
{
    public static function atPath(string $path): self
    {
        return new self('Path is not a file: '.$path);
    }
}
