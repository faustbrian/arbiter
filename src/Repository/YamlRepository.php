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
 * Requires symfony/yaml package to be installed. Supports two loading modes:
 * single-file mode loads one or more policies from a single YAML file, while
 * per-file mode loads individual policies from separate YAML files (.yml or .yaml)
 * in a directory. All policies are cached in memory after initial load.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class YamlRepository implements PolicyRepositoryInterface
{
    /** @var array<string, Policy> Loaded policies indexed by name */
    private array $policies = [];

    /**
     * Create a new YAML-backed policy repository.
     *
     * @param string $path    Absolute path to YAML file (if $perFile is false) or directory (if $perFile is true)
     * @param bool   $perFile If true, loads from directory with one policy per .yml/.yaml file.
     *                        If false, loads from single YAML file containing one or more policies.
     *
     * @throws SymfonyYamlRequiredToUseYamlRepositoryException If symfony/yaml is not installed
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

    /**
     * Retrieve a policy by name.
     *
     * @param string $name The unique name of the policy to retrieve
     *
     * @throws PolicyNotFoundException If the policy does not exist in the repository
     */
    public function get(string $name): Policy
    {
        throw_unless($this->has($name), PolicyNotFoundException::forName($name));

        return $this->policies[$name];
    }

    /**
     * Check if a policy exists in the repository.
     *
     * @param  string $name The unique name of the policy to check
     * @return bool   True if the policy exists, false otherwise
     */
    public function has(string $name): bool
    {
        return isset($this->policies[$name]);
    }

    /**
     * Retrieve all policies in the repository.
     *
     * @return array<string, Policy> All loaded policies indexed by name
     */
    public function all(): array
    {
        return $this->policies;
    }

    /**
     * Retrieve multiple policies by their names.
     *
     * @param array<string> $names Array of policy names to retrieve
     *
     * @throws MultiplePoliciesNotFoundException If any requested policy does not exist
     * @return array<string, Policy>             Policies indexed by name
     */
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
     *
     * Supports both single policy and array of policies in the YAML structure.
     * If the YAML contains a 'name' field, treats it as a single policy.
     * Otherwise, treats it as an array of policy definitions.
     *
     * @param string $path Absolute path to the YAML file
     *
     * @throws InvalidPolicyStructureException If any policy has invalid structure
     * @throws InvalidYamlException            If the YAML is invalid or not an array
     * @throws PathIsNotFileException          If the path is not a file
     * @throws YamlFileNotFoundException       If the file does not exist
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
     *
     * Scans the directory for all .yml and .yaml files and loads each as a separate policy.
     * Each YAML file must contain exactly one policy definition with a 'name' field.
     *
     * @param string $path Absolute path to directory containing YAML policy files
     *
     * @throws DirectoryNotFoundException      If the directory does not exist
     * @throws FailedToReadDirectoryException  If the directory cannot be scanned
     * @throws InvalidPolicyStructureException If any policy has invalid structure
     * @throws InvalidYamlException            If any file contains invalid YAML
     * @throws NoYamlFilesFoundException       If no .yml or .yaml files are found in the directory
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
