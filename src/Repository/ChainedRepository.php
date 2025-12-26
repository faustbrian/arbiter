<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Repository;

use Cline\Arbiter\Exception\EmptyChainedRepositoryException;
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;

use function array_any;
use function array_diff;
use function array_keys;
use function array_merge;
use function array_reverse;

/**
 * Try multiple repositories in order (fallback chain).
 *
 * Searches repositories in the order provided, returning the first match found.
 * Useful for implementing configuration overrides (local -> database -> defaults).
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ChainedRepository implements PolicyRepositoryInterface
{
    /**
     * @param  array<PolicyRepositoryInterface> $repositories Repositories to chain, in order of priority
     * @throws EmptyChainedRepositoryException  If no repositories are provided
     */
    public function __construct(
        private array $repositories,
    ) {
        if ($this->repositories === []) {
            throw EmptyChainedRepositoryException::create();
        }
    }

    /**
     * Get a policy by name.
     *
     * Searches repositories in order, returning the first match found.
     *
     * @param  string                  $name The policy name
     * @throws PolicyNotFoundException If the policy is not found in any repository
     * @return Policy                  The policy instance
     */
    public function get(string $name): Policy
    {
        foreach ($this->repositories as $repository) {
            if ($repository->has($name)) {
                return $repository->get($name);
            }
        }

        throw PolicyNotFoundException::forName($name);
    }

    /**
     * Check if a policy exists.
     *
     * Returns true if any repository in the chain has the policy.
     *
     * @param  string $name The policy name
     * @return bool   True if the policy exists, false otherwise
     */
    public function has(string $name): bool
    {
        return array_any($this->repositories, fn ($repository): bool => $repository->has($name));
    }

    /**
     * Get all policies.
     *
     * Merges policies from all repositories, with earlier repositories
     * taking precedence for duplicate names.
     *
     * @return array<string, Policy> Map of policy names to instances
     */
    public function all(): array
    {
        $policies = [];

        // Iterate in reverse order so earlier repositories override later ones
        foreach (array_reverse($this->repositories) as $repository) {
            $policies = array_merge($policies, $repository->all());
        }

        return $policies;
    }

    /**
     * Get multiple policies.
     *
     * For each name, returns the policy from the first repository that has it.
     *
     * @param  array<string>         $names The policy names to retrieve
     * @return array<string, Policy> Map of policy names to instances
     */
    public function getMany(array $names): array
    {
        $result = [];
        $remaining = $names;

        foreach ($this->repositories as $repository) {
            if ($remaining === []) {
                break;
            }

            $found = $repository->getMany($remaining);
            $result = array_merge($result, $found);

            // Remove found names from remaining
            $remaining = array_diff($remaining, array_keys($found));
        }

        return $result;
    }
}
