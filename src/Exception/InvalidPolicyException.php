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
 * Exception thrown when a policy definition fails validation.
 *
 * Indicates that a policy's structure, configuration, or content does not
 * conform to required specifications. This includes missing required fields,
 * invalid field types, malformed rules, or policy definitions that violate
 * the policy schema constraints.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidPolicyException extends RuntimeException implements ArbiterException {}
