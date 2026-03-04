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
use Cline\Arbiter\Exception\FailedToReadJsonFileException;
use Cline\Arbiter\Exception\InvalidJsonException;
use Cline\Arbiter\Exception\InvalidPolicyStructureException;
use Cline\Arbiter\Exception\JsonFileNotFoundException;
use Cline\Arbiter\Exception\MultiplePoliciesNotFoundException;
use Cline\Arbiter\Exception\NoJsonFilesFoundException;
use Cline\Arbiter\Exception\PathIsNotFileException;
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;

use function file_exists;
use function file_get_contents;
use function glob;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function throw_if;
use function throw_unless;

/**
 * Policy repository backed by JSON files.
 *
 * Supports two loading modes: single-file mode loads one or more policies
 * from a single JSON file, while per-file mode loads individual policies
 * from separate JSON files in a directory. All policies are cached in memory
 * after initial load for fast access.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class JsonRepository implements PolicyRepositoryInterface
{
    /** @var array<string, Policy> Loaded policies indexed by name */
    private array $policies = [];

    /**
     * Create a new JSON-backed policy repository.
     *
     * @param string $path    Absolute path to JSON file (if $perFile is false) or directory (if $perFile is true)
     * @param bool   $perFile If true, loads from directory with one policy per .json file.
     *                        If false, loads from single JSON file containing one or more policies.
     */
    public function __construct(string $path, bool $perFile = false)
    {
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
     * Load policies from a single JSON file.
     *
     * Supports both single policy and array of policies in the JSON structure.
     * If the JSON contains a 'name' field, treats it as a single policy.
     * Otherwise, treats it as an array of policy definitions.
     *
     * @param string $path Absolute path to the JSON file
     *
     * @throws FailedToReadJsonFileException   If the file cannot be read
     * @throws InvalidJsonException            If the JSON is invalid or not an array
     * @throws InvalidPolicyStructureException If any policy has invalid structure
     * @throws JsonFileNotFoundException       If the file does not exist
     * @throws PathIsNotFileException          If the path is not a file
     */
    private function loadFromFile(string $path): void
    {
        throw_unless(file_exists($path), JsonFileNotFoundException::atPath($path));

        throw_unless(is_file($path), PathIsNotFileException::atPath($path));

        $contents = file_get_contents($path);

        throw_if($contents === false, FailedToReadJsonFileException::atPath($path));

        $data = json_decode($contents, true);

        throw_unless(is_array($data), InvalidJsonException::inFile($path));

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
     * Load policies from a directory of JSON files.
     *
     * Scans the directory for all .json files and loads each as a separate policy.
     * Each JSON file must contain exactly one policy definition with a 'name' field.
     *
     * @param string $path Absolute path to directory containing JSON policy files
     *
     * @throws DirectoryNotFoundException      If the directory does not exist
     * @throws FailedToReadDirectoryException  If the directory cannot be scanned
     * @throws FailedToReadJsonFileException   If any JSON file cannot be read
     * @throws InvalidJsonException            If any file contains invalid JSON
     * @throws InvalidPolicyStructureException If any policy has invalid structure
     * @throws NoJsonFilesFoundException       If no .json files are found in the directory
     */
    private function loadFromDirectory(string $path): void
    {
        throw_unless(is_dir($path), DirectoryNotFoundException::atPath($path));

        $files = glob($path.'/*.json');

        throw_if($files === false, FailedToReadDirectoryException::atPath($path));

        throw_if($files === [], NoJsonFilesFoundException::inDirectory($path));

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            throw_if($contents === false, FailedToReadJsonFileException::atPath($file));

            $data = json_decode($contents, true);

            throw_unless(is_array($data), InvalidJsonException::inFile($file));

            throw_if(!isset($data['name']) || !is_string($data['name']), InvalidPolicyStructureException::inFile($file));

            /** @var array{name: string, description?: string, rules?: array<array{path: string, effect?: string, capabilities?: array<string>, conditions?: array<string, mixed>, description?: string}>} $data */
            $policy = Policy::fromArray($data);
            $this->policies[$policy->getName()] = $policy;
        }
    }
}
