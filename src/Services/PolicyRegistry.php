<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Services;

use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Repository\PolicyRepositoryInterface;

/**
 * Central registry for managing policies in memory with optional repository backing.
 *
 * Provides a two-tier policy storage system: policies can be registered directly
 * in memory for fast access, or loaded on-demand from a configured repository.
 * The registry serves as a cache layer over the repository.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PolicyRegistry
{
    /** @var array<string, Policy> In-memory cache of registered policies indexed by name */
    private array $policies = [];

    /** @var null|PolicyRepositoryInterface Optional repository for loading policies on-demand */
    private ?PolicyRepositoryInterface $repository = null;

    /**
     * Add a policy to the in-memory registry.
     *
     * @param Policy $policy The policy to register
     */
    public function add(Policy $policy): void
    {
        $this->policies[$policy->getName()] = $policy;
    }

    /**
     * Get a policy by name from the registry or repository.
     *
     * Searches the in-memory cache first, then falls back to the configured
     * repository if available. Policies loaded from the repository are cached
     * in memory for subsequent access.
     *
     * @param string $name The unique name of the policy to retrieve
     *
     * @throws PolicyNotFoundException If the policy is not found in cache or repository
     * @return Policy                  The requested policy instance
     */
    public function get(string $name): Policy
    {
        if (isset($this->policies[$name])) {
            return $this->policies[$name];
        }

        if ($this->repository instanceof PolicyRepositoryInterface && $this->repository->has($name)) {
            $policy = $this->repository->get($name);
            $this->policies[$name] = $policy;

            return $policy;
        }

        throw PolicyNotFoundException::forName($name);
    }

    /**
     * Check if a policy exists in the registry or repository.
     *
     * Checks both the in-memory cache and the configured repository (if available).
     *
     * @param  string $name The unique name of the policy to check
     * @return bool   True if the policy exists in cache or repository
     */
    public function has(string $name): bool
    {
        return isset($this->policies[$name])
            || ($this->repository instanceof PolicyRepositoryInterface && $this->repository->has($name));
    }

    /**
     * Set the policy repository for on-demand loading.
     *
     * Configures a repository that will be used to load policies not found
     * in the in-memory cache. Loaded policies are cached for future access.
     *
     * @param PolicyRepositoryInterface $repository The repository to use for policy loading
     */
    public function setRepository(PolicyRepositoryInterface $repository): void
    {
        $this->repository = $repository;
    }

    /**
     * Get all policies currently cached in memory.
     *
     * Returns only policies that have been explicitly registered or loaded
     * from the repository. Does not load all policies from the repository.
     *
     * @return array<string, Policy> Cached policies indexed by name
     */
    public function all(): array
    {
        return $this->policies;
    }
}
