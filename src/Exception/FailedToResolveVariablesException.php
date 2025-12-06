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
 * Exception thrown when variable resolution in policy rules fails.
 *
 * Policy rules can contain variable placeholders that need to be resolved
 * with actual values during evaluation. This exception occurs when the
 * regular expression operations used for variable substitution fail,
 * typically due to invalid patterns or encoding issues.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FailedToResolveVariablesException extends RuntimeException implements ArbiterException
{
    /**
     * Create an exception for when preg_replace_callback returns null during resolution.
     *
     * This indicates a critical error in the regex pattern or callback processing,
     * as preg_replace_callback only returns null on error, preventing variable
     * substitution from completing successfully.
     *
     * @return self The exception instance with diagnostic information
     */
    public static function pregReplaceCallbackReturnedNull(): self
    {
        return new self('Failed to resolve variables: preg_replace_callback returned null');
    }
}
