<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

use InvalidArgumentException;

/**
 * Base exception for policy data validation errors.
 * @author Brian Faust <brian@cline.sh>
 */
abstract class InvalidPolicyDataException extends InvalidArgumentException implements ArbiterException {}
