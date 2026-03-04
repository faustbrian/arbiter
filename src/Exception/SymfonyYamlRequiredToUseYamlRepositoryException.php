<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

/**
 * Exception thrown when attempting to use YamlRepository without symfony/yaml installed.
 *
 * YamlRepository requires the symfony/yaml component to parse YAML files.
 * Install it via: composer require symfony/yaml
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SymfonyYamlRequiredToUseYamlRepositoryException extends SymfonyYamlRequiredException
{
    /**
     * Create a new exception instance.
     */
    public static function create(): self
    {
        return new self('symfony/yaml is required to use YamlRepository');
    }
}
