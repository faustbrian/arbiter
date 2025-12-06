<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Repository;

use Cline\Arbiter\Exception\DirectoryNotFoundException;
use Cline\Arbiter\Exception\FailedToReadDirectoryException;
use Cline\Arbiter\Exception\InvalidPolicyStructureException;
use Cline\Arbiter\Exception\InvalidYamlException;
use Cline\Arbiter\Exception\MultiplePoliciesNotFoundException;
use Cline\Arbiter\Exception\NoYamlFilesFoundException;
use Cline\Arbiter\Exception\PathIsNotFileException;
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Exception\SymfonyYamlRequiredToUseYamlRepositoryException;
use Cline\Arbiter\Exception\YamlFileNotFoundException;
use Cline\Arbiter\Policy;
use Symfony\Component\Yaml\Yaml;

use const GLOB_BRACE;

use function class_exists;
use function file_exists;
use function glob;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function throw_if;
use function throw_unless;

/**
 * Policy repository backed by YAML files.
 *
 * Requires symfony/yaml package to be installed.
 * Supports loading from a single YAML file containing multiple policies,
 * or from a directory of individual YAML files (one policy per file).
 * @author Brian Faust <brian@cline.sh>
 */
final class YamlRepository implements PolicyRepositoryInterface
{
    /** @var array<string, Policy> */
    private array $policies = [];

    /**
     * @param string $path    Path to YAML file or directory
     * @param bool   $perFile If true, treat path as directory with one policy per file
     */
    public function __construct(string $path, bool $perFile = false)
    {
        throw_unless(class_exists(Yaml::class), SymfonyYamlRequiredToUseYamlRepositoryException::create());

        if ($perFile) {
            $this->loadFromDirectory($path);
        } else {
            $this->loadFromFile($path);
        }
    }

    public function get(string $name): Policy
    {
        throw_unless($this->has($name), PolicyNotFoundException::forName($name));

        return $this->policies[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->policies[$name]);
    }

    public function all(): array
    {
        return $this->policies;
    }

    public function getMany(array $names): array
    {
        $missing = [];
        $policies = [];

        foreach ($names as $name) {
            if ($this->has($name)) {
                $policies[$name] = $this->policies[$name];
            } else {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            $nameList = implode(', ', $missing);

            throw MultiplePoliciesNotFoundException::forNames($nameList);
        }

        return $policies;
    }

    /**
     * Load policies from a single YAML file.
     */
    private function loadFromFile(string $path): void
    {
        throw_unless(file_exists($path), YamlFileNotFoundException::atPath($path));

        throw_unless(is_file($path), PathIsNotFileException::atPath($path));

        $data = Yaml::parseFile($path);

        throw_unless(is_array($data), InvalidYamlException::inFile($path));

        // Support both array of policies and single policy
        if (isset($data['name'])) {
            // Single policy - validate structure
            throw_unless(is_string($data['name']), InvalidPolicyStructureException::inFile($path));

            /** @var array{name: string, description?: string, rules?: array<array{path: string, effect?: string, capabilities?: array<string>, conditions?: array<string, mixed>, description?: string}>} $data */
            $policy = Policy::fromArray($data);
            $this->policies[$policy->getName()] = $policy;
        } else {
            // Array of policies
            foreach ($data as $policyData) {
                if (!is_array($policyData)) {
                    continue;
                }

                throw_if(!isset($policyData['name']) || !is_string($policyData['name']), InvalidPolicyStructureException::inFile($path));

                /** @var array{name: string, description?: string, rules?: array<array{path: string, effect?: string, capabilities?: array<string>, conditions?: array<string, mixed>, description?: string}>} $policyData */
                $policy = Policy::fromArray($policyData);
                $this->policies[$policy->getName()] = $policy;
            }
        }
    }

    /**
     * Load policies from a directory of YAML files.
     */
    private function loadFromDirectory(string $path): void
    {
        throw_unless(is_dir($path), DirectoryNotFoundException::atPath($path));

        $files = glob($path.'/*.{yml,yaml}', GLOB_BRACE);

        throw_if($files === false, FailedToReadDirectoryException::atPath($path));

        throw_if($files === [], NoYamlFilesFoundException::inDirectory($path));

        foreach ($files as $file) {
            $data = Yaml::parseFile($file);

            throw_unless(is_array($data), InvalidYamlException::inFile($file));

            throw_if(!isset($data['name']) || !is_string($data['name']), InvalidPolicyStructureException::inFile($file));

            /** @var array{name: string, description?: string, rules?: array<array{path: string, effect?: string, capabilities?: array<string>, conditions?: array<string, mixed>, description?: string}>} $data */
            $policy = Policy::fromArray($data);
            $this->policies[$policy->getName()] = $policy;
        }
    }
}
