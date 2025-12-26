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
 * Base exception for policy data validation failures.
 *
 * Serves as the parent exception for all policy-related validation errors that
 * occur when policy data fails to meet schema requirements, contains invalid
 * values, or violates business rules. Concrete implementations should extend
 * this class for specific validation error scenarios.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class InvalidPolicyDataException extends InvalidArgumentException implements ArbiterException {}
