<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Repository;

use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;

/**
 * Interface for policy storage and retrieval implementations.
 *
 * Defines the contract for policy repositories supporting single and batch
 * retrieval operations. Implementations may load policies from various sources
 * including memory, JSON files, YAML files, or databases.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface PolicyRepositoryInterface
{
    /**
     * Retrieve a policy by its unique name.
     *
     * @param string $name The unique identifier of the policy
     *
     * @throws PolicyNotFoundException If the policy does not exist in the repository
     * @return Policy                  The requested policy instance
     */
    public function get(string $name): Policy;

    /**
     * Check if a policy exists in the repository.
     *
     * @param  string $name The unique identifier of the policy to check
     * @return bool   True if the policy exists, false otherwise
     */
    public function has(string $name): bool;

    /**
     * Retrieve all policies from the repository.
     *
     * @return array<string, Policy> All policies indexed by their unique names
     */
    public function all(): array;

    /**
     * Retrieve multiple policies by their names in a single operation.
     *
     * More efficient than multiple individual get() calls when retrieving
     * several policies at once. All requested policies must exist.
     *
     * @param array<string> $names Array of unique policy names to retrieve
     *
     * @throws PolicyNotFoundException If any requested policy does not exist
     * @return array<string, Policy>   Requested policies indexed by their names
     */
    public function getMany(array $names): array;
}
