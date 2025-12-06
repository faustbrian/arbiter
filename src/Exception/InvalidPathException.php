<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

/**
 * Exception thrown when a path format is invalid.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidPathException extends \RuntimeException implements ArbiterException {}
