<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter;

/**
 * Represents the authorization effect of a policy rule evaluation.
 *
 * In policy-based access control systems, every rule produces an effect
 * that determines whether an action should be permitted or forbidden.
 * This enum provides the two fundamental authorization outcomes.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum Effect: string
{
    /**
     * Permits the requested action.
     *
     * When a rule evaluates to Allow, the action is granted and the
     * request is authorized to proceed.
     */
    case Allow = 'allow';

    /**
     * Forbids the requested action.
     *
     * When a rule evaluates to Deny, the action is rejected and the
     * request is not authorized. Deny effects typically take precedence
     * over Allow effects in policy evaluation.
     */
    case Deny = 'deny';
}
