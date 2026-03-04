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
 * Exception thrown when policy repository operations encounter errors.
 *
 * This general-purpose exception covers various repository-related failures
 * that occur during policy loading, storage, or retrieval operations. Common
 * scenarios include file system errors, parsing failures, or data integrity
 * issues when working with policy repositories (JSON, YAML, or custom sources).
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RepositoryException extends RuntimeException implements ArbiterException {}
