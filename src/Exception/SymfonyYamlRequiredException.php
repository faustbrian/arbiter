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
 * Base exception for errors when symfony/yaml package is required but missing.
 *
 * This abstract exception serves as the parent for all exceptions related to
 * missing symfony/yaml dependency. The package is an optional dependency that
 * enables YAML policy file support. Subclasses provide specific error messages
 * for different contexts where the YAML component is needed but not installed.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class SymfonyYamlRequiredException extends RuntimeException implements ArbiterException {}
