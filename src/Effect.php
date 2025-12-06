<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter;

/**
 * Represents the effect of a policy rule.
 *
 * Allow permits the action, while Deny forbids it.
 * @author Brian Faust <brian@cline.sh>
 */
enum Effect: string
{
    case Allow = 'allow';
    case Deny = 'deny';
}
