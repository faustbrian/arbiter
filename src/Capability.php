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
 * Represents authorization capabilities that can be granted or denied in policies.
 *
 * Defines standard CRUD operations plus list and admin capabilities.
 * The Admin capability has special privileges and implies all other capabilities,
 * making it suitable for superuser or administrative access patterns.
 *
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
     * Performs case-insensitive matching to create the appropriate capability
     * enum case. Useful when parsing capabilities from configuration files,
     * user input, or external data sources.
     *
     * @param string $value Capability name (case-insensitive)
     *
     * @throws ValueError When the value does not match any valid capability
     * @return self       The matching capability enum case
     */
    public static function fromString(string $value): self
    {
        return self::from(mb_strtolower($value));
    }

    /**
     * Check if this capability implies another capability.
     *
     * The Admin capability implies all other capabilities, enabling a single
     * permission to grant full access. Other capabilities only imply themselves.
     *
     * @param Capability $other The capability to check against
     *
     * @return bool True if this capability grants the other capability, false otherwise
     */
    public function implies(Capability $other): bool
    {
        if ($this === self::Admin) {
            return true;
        }

        return $this === $other;
    }
}
