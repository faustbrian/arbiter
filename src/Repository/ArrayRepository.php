<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Repository;

use Cline\Arbiter\Exception\MultiplePoliciesNotFoundException;
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;

use function implode;
use function throw_unless;

/**
 * In-memory policy repository backed by an array.
 *
 * Provides fast access to policies stored in memory. Ideal for testing,
 * programmatic policy definition, or applications with a small number of
 * policies. All policies are indexed by name for constant-time lookups.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ArrayRepository implements PolicyRepositoryInterface
{
    /** @var array<string, Policy> Policies indexed by name for fast lookups */
    private array $policies = [];

    /**
     * Create a new in-memory policy repository.
     *
     * @param array<Policy> $policies Initial policies to store, indexed automatically by name
     */
    public function __construct(array $policies = [])
    {
        foreach ($policies as $policy) {
            $this->policies[$policy->getName()] = $policy;
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
     * @return array<string, Policy> All policies indexed by name
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
}
