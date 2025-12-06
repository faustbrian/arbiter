<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter;

use ValueError;

use function mb_strtolower;

/**
 * Represents capabilities that can be granted or denied.
 *
 * Admin capability implies all other capabilities.
 * @author Brian Faust <brian@cline.sh>
 */
enum Capability: string
{
    case Read = 'read';
    case List = 'list';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Admin = 'admin';

    /**
     * Create a capability from a string value.
     *
     * @throws ValueError if the value is not a valid capability
     */
    public static function fromString(string $value): self
    {
        return self::from(mb_strtolower($value));
    }

    /**
     * Check if this capability implies another.
     * Admin implies all others.
     */
    public function implies(Capability $other): bool
    {
        if ($this === self::Admin) {
            return true;
        }

        return $this === $other;
    }
}
