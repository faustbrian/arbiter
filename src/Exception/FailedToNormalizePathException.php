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
 * Exception thrown when path normalization fails.
 * @author Brian Faust <brian@cline.sh>
 */
final class FailedToNormalizePathException extends RuntimeException implements ArbiterException
{
    public static function pregReplaceReturnedNull(): self
    {
        return new self('Failed to normalize path: preg_replace returned null');
    }
}
