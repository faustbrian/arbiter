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
 * Base exception for symfony/yaml requirement errors.
 * @author Brian Faust <brian@cline.sh>
 */
abstract class SymfonyYamlRequiredException extends RuntimeException implements ArbiterException {}
