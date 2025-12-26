<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter;

use Cline\Arbiter\Conductors\PathEvaluationConductor;
use Cline\Arbiter\Conductors\PolicyEvaluationConductor;
use Cline\Arbiter\Repository\PolicyRepositoryInterface;
use Cline\Arbiter\Services\EvaluationService;
use Cline\Arbiter\Services\PolicyRegistry;

use function array_map;
use function is_string;

/**
 * Central manager for policy-based access control and authorization.
 *
 * Provides the primary interface for evaluating policies against paths and
 * capabilities. Supports both policy-centric and path-centric evaluation
 * workflows through conductor pattern implementations.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ArbiterManager
{
    /**
     * Create a new arbiter manager instance.
     *
     * @param PolicyRegistry    $registry  Registry holding all registered policies for lookup and resolution
     * @param EvaluationService $evaluator Service responsible for evaluating policies against paths and capabilities
     */
    public function __construct(
        private PolicyRegistry $registry,
        private EvaluationService $evaluator,
    ) {}

    /**
     * Create a policy evaluation conductor for the given policy.
     *
     * Initiates a policy-centric evaluation flow where you start with one or
     * more policies and then specify paths and capabilities to check.
     *
     * @param array<Policy|string>|Policy|string $policy Single policy instance/name or array of policies
     *
     * @return PolicyEvaluationConductor Fluent conductor for chaining path and capability checks
     */
    public function for(string|array|Policy $policy): PolicyEvaluationConductor
    {
        return new PolicyEvaluationConductor(
            policies: $this->resolvePolicies($policy),
            evaluator: $this->evaluator,
        );
    }

    /**
     * Create a path evaluation conductor for the given path.
     *
     * Initiates a path-centric evaluation flow where you start with a resource
     * path and then specify which policies to evaluate against it.
     *
     * @param string $path Resource path to evaluate (e.g., 'users/*', 'projects/123')
     *
     * @return PathEvaluationConductor Fluent conductor for specifying policies and checking capabilities
     */
    public function path(string $path): PathEvaluationConductor
    {
        return new PathEvaluationConductor(
            path: $path,
            evaluator: $this->evaluator,
            registry: $this->registry,
        );
    }

    /**
     * Set the policy repository for loading policies from external storage.
     *
     * @param PolicyRepositoryInterface $repository Repository implementation for policy storage and retrieval
     *
     * @return self Returns the manager instance for method chaining
     */
    public function repository(PolicyRepositoryInterface $repository): self
    {
        $this->registry->setRepository($repository);

        return $this;
    }

    /**
     * Register a policy in the registry.
     *
     * @param Policy $policy Policy instance to register for evaluation
     */
    public function register(Policy $policy): void
    {
        $this->registry->add($policy);
    }

    /**
     * Check if a policy is registered by name.
     *
     * @param string $name Policy name to check
     *
     * @return bool True if policy exists in registry, false otherwise
     */
    public function has(string $name): bool
    {
        return $this->registry->has($name);
    }

    /**
     * Get a registered policy by name.
     *
     * @param string $name Policy name to retrieve
     *
     * @return Policy The registered policy instance
     */
    public function get(string $name): Policy
    {
        return $this->registry->get($name);
    }

    /**
     * Get all registered policies.
     *
     * @return array<Policy> Array of all registered policy instances
     */
    public function all(): array
    {
        return $this->registry->all();
    }

    /**
     * Resolve policy names/objects to Policy instances.
     *
     * Handles three input formats: single Policy instance, single policy name string,
     * or array of mixed Policy instances and policy names. All string names are
     * resolved through the registry.
     *
     * @param array<Policy|string>|Policy|string $policy Policy instance(s) or name(s) to resolve
     *
     * @return array<Policy> Array of resolved Policy instances
     */
    private function resolvePolicies(string|array|Policy $policy): array
    {
        if ($policy instanceof Policy) {
            return [$policy];
        }

        if (is_string($policy)) {
            return [$this->registry->get($policy)];
        }

        return array_map(
            fn (string|Policy $p): Policy => is_string($p) ? $this->registry->get($p) : $p,
            $policy,
        );
    }
}
