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
 * Exception thrown when path normalization fails unexpectedly.
 *
 * Path normalization is required to ensure consistent path comparisons
 * and matching in the policy engine. This exception occurs when the
 * regular expression operations used for normalization fail, typically
 * due to invalid regex patterns or encoding issues.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FailedToNormalizePathException extends RuntimeException implements ArbiterException
{
    /**
     * Create an exception for when preg_replace returns null during normalization.
     *
     * This indicates a critical error in the regex pattern or the input string,
     * as preg_replace only returns null on error (not even on non-matches).
     *
     * @return self The exception instance with diagnostic information
     */
    public static function pregReplaceReturnedNull(): self
    {
        return new self('Failed to normalize path: preg_replace returned null');
    }
}
