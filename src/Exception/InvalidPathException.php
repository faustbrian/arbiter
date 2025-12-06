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
 * Exception thrown when a file or directory path format is invalid.
 *
 * Indicates that a provided path does not meet validation requirements,
 * such as containing invalid characters, exceeding length limits, or
 * violating path structure constraints for the policy system.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidPathException extends RuntimeException implements ArbiterException {}
