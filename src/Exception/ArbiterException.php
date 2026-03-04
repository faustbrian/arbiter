<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

use Throwable;

/**
 * Marker interface for all Arbiter package exceptions.
 *
 * This interface allows consumers to catch any exception thrown by the
 * Arbiter package in a unified way, making error handling simpler and
 * more maintainable when integrating the policy engine.
 *
 * ```php
 * try {
 *     $arbiter->can($path, $capability);
 * } catch (ArbiterException $e) {
 *     // Handle any Arbiter-specific error
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ArbiterException extends Throwable {}
