<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

/**
 * Exception thrown when a requested policy is not found.
 * @author Brian Faust <brian@cline.sh>
 */
final class PolicyNotFoundException extends ArbiterException
{
    public static function forName(string $name): self
    {
        return new self('Policy not found: '.$name);
    }
}
