<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

/**
 * Exception thrown when symfony/yaml is required to load YAML files.
 * @author Brian Faust <brian@cline.sh>
 */
final class SymfonyYamlRequiredToLoadYamlFilesException extends SymfonyYamlRequiredException
{
    public static function create(): self
    {
        return new self('symfony/yaml is required to load YAML files');
    }
}
