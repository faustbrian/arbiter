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
 * Interface for policy storage and retrieval.
 * @author Brian Faust <brian@cline.sh>
 */
interface PolicyRepositoryInterface
{
    /**
     * Retrieve a policy by name.
     *
     * @throws PolicyNotFoundException If the policy does not exist
     */
    public function get(string $name): Policy;

    /**
     * Check if a policy exists.
     */
    public function has(string $name): bool;

    /**
     * Retrieve all policies.
     *
     * @return array<string, Policy> Policies keyed by name
     */
    public function all(): array;

    /**
     * Retrieve multiple policies by name.
     *
     * @param  array<string>           $names
     * @throws PolicyNotFoundException If any policy does not exist
     * @return array<string, Policy>   Policies keyed by name
     */
    public function getMany(array $names): array;
}
