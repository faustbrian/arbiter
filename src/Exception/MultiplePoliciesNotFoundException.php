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
 * Exception thrown when multiple policies are not found.
 * @author Brian Faust <brian@cline.sh>
 */
final class MultiplePoliciesNotFoundException extends RuntimeException implements ArbiterException
{
    public static function forNames(string $nameList): self
    {
        return new self('Policies not found: '.$nameList);
    }
}
