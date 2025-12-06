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
 * Exception thrown when variable resolution fails.
 * @author Brian Faust <brian@cline.sh>
 */
final class FailedToResolveVariablesException extends RuntimeException implements ArbiterException
{
    public static function pregReplaceCallbackReturnedNull(): self
    {
        return new self('Failed to resolve variables: preg_replace_callback returned null');
    }
}
