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
 * @author Brian Faust <brian@cline.sh>
 */
final class PolicyRegistry
{
    /** @var array<string, Policy> */
    private array $policies = [];

    private ?PolicyRepositoryInterface $repository = null;

    /**
     * Add a policy to the registry.
     */
    public function add(Policy $policy): void
    {
        $this->policies[$policy->getName()] = $policy;
    }

    /**
     * Get a policy by name.
     *
     * @throws PolicyNotFoundException
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
     * Check if a policy exists.
     */
    public function has(string $name): bool
    {
        return isset($this->policies[$name])
            || ($this->repository instanceof PolicyRepositoryInterface && $this->repository->has($name));
    }

    /**
     * Set the policy repository.
     */
    public function setRepository(PolicyRepositoryInterface $repository): void
    {
        $this->repository = $repository;
    }

    /**
     * Get all registered policies.
     *
     * @return array<string, Policy>
     */
    public function all(): array
    {
        return $this->policies;
    }
}
