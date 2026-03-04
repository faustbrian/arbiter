<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Exception;

/**
 * Exception thrown when attempting to load YAML policy files without symfony/yaml.
 *
 * This exception occurs when the application attempts to parse or load YAML-formatted
 * policy files but the symfony/yaml component is not installed. To use YAML policy
 * files, install the package via composer: composer require symfony/yaml. Alternatively,
 * convert policy files to JSON format which is supported without additional dependencies.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SymfonyYamlRequiredToLoadYamlFilesException extends SymfonyYamlRequiredException
{
    /**
     * Create an exception for YAML file loading when symfony/yaml is missing.
     *
     * @return self The exception instance with installation instructions
     */
    public static function create(): self
    {
        return new self('symfony/yaml is required to load YAML files');
    }
}
