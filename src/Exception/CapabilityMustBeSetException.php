<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

use LogicException;

/**
 * Exception thrown when capability must be set before listing paths.
 * @author Brian Faust <brian@cline.sh>
 */
final class CapabilityMustBeSetException extends LogicException implements ArbiterException
{
    public static function beforeListingPaths(): self
    {
        return new self('Capability must be set before listing accessible paths. Use can($path, $capability) first.');
    }
}
