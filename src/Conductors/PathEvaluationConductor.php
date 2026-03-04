<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Conductors;

use Cline\Arbiter\Capability;
use Cline\Arbiter\Exception\PoliciesMustBeSetException;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Services\EvaluationService;
use Cline\Arbiter\Services\PolicyRegistry;

use function array_map;
use function array_merge;
use function is_string;
use function throw_if;

/**
 * Fluent conductor for path-centric policy evaluation.
 *
 * Provides a path-first evaluation workflow where you start with a resource path
 * and then specify which policies to evaluate against it. Uses immutable builder
 * pattern to construct the evaluation context through method chaining.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class PathEvaluationConductor
{
    /**
     * Create a new path evaluation conductor.
     *
     * @param string               $path      Resource path to evaluate (e.g., 'users/*', 'projects/123')
     * @param EvaluationService    $evaluator Service responsible for executing policy evaluation logic
     * @param PolicyRegistry       $registry  Registry for resolving policy names to instances
     * @param array<Policy>        $policies  Array of policies to evaluate against the path
     * @param array<string, mixed> $context   Additional context data for condition evaluation
     */
    public function __construct(
        private string $path,
        private EvaluationService $evaluator,
        private PolicyRegistry $registry,
        private array $policies = [],
        private array $context = [],
    ) {}

    /**
     * Set which policies to check against.
     *
     * Specifies the policy or policies that should be evaluated for this path.
     * Returns a new conductor instance with the policies set, following the
     * immutable builder pattern.
     *
     * @param array<Policy|string>|string $policies Single policy or array of policies (as instances or names)
     *
     * @return self New conductor instance with policies configured
     */
    public function against(string|array $policies): self
    {
        $resolved = $this->resolvePolicies($policies);

        return new self(
            path: $this->path,
            evaluator: $this->evaluator,
            registry: $this->registry,
            policies: $resolved,
            context: $this->context,
        );
    }

    /**
     * Add context for evaluation.
     *
     * Merges additional context data that will be available for condition evaluation.
     * Context values are matched against policy condition definitions to determine
     * if a policy applies to the current request.
     *
     * @param array<string, mixed> $context Additional context data to merge with existing context
     *
     * @return self New conductor instance with merged context
     */
    public function with(array $context): self
    {
        return new self(
            path: $this->path,
            evaluator: $this->evaluator,
            registry: $this->registry,
            policies: $this->policies,
            context: array_merge($this->context, $context),
        );
    }

    /**
     * Get all available capabilities at this path.
     *
     * Evaluates the configured policies and returns the set of capabilities
     * that are granted for the current path and context. Useful for determining
     * what operations are permitted without checking each capability individually.
     *
     * @throws PoliciesMustBeSetException When policies have not been set via against()
     * @return array<Capability>          Array of capabilities granted by the policies
     */
    public function capabilities(): array
    {
        $this->ensurePolicies();

        return $this->evaluator->getCapabilities(
            policies: $this->policies,
            path: $this->path,
            context: $this->context,
        );
    }

    /**
     * Check if a specific capability is allowed.
     *
     * Evaluates whether the specified capability is granted for the configured
     * path and context according to the registered policies.
     *
     * @param Capability $capability The capability to check for authorization
     *
     * @throws PoliciesMustBeSetException When policies have not been set via against()
     * @return bool                       True if the capability is allowed, false otherwise
     */
    public function allows(Capability $capability): bool
    {
        $this->ensurePolicies();

        return $this->evaluator->evaluate(
            policies: $this->policies,
            capability: $capability,
            path: $this->path,
            context: $this->context,
        )->isAllowed();
    }

    /**
     * Check if a specific capability is denied.
     *
     * Inverse of allows() - returns true when the capability is not granted.
     *
     * @param Capability $capability The capability to check for denial
     *
     * @throws PoliciesMustBeSetException When policies have not been set via against()
     * @return bool                       True if the capability is denied, false if allowed
     */
    public function denies(Capability $capability): bool
    {
        return !$this->allows($capability);
    }

    /**
     * Ensure policies have been configured before evaluation.
     *
     * @throws PoliciesMustBeSetException When policies array is empty
     */
    private function ensurePolicies(): void
    {
        throw_if($this->policies === [], PoliciesMustBeSetException::beforeEvaluation());
    }

    /**
     * Resolve policy names/objects to Policy instances.
     *
     * Normalizes mixed policy input (strings/instances) into an array of Policy
     * objects by resolving string names through the registry. Handles both single
     * policy values and arrays of policies.
     *
     * @param array<Policy|string>|string $policies Policy instance(s) or name(s) to resolve
     *
     * @return array<Policy> Array of resolved Policy instances
     */
    private function resolvePolicies(string|array $policies): array
    {
        $policies = is_string($policies) ? [$policies] : $policies;

        return array_map(
            fn (string|Policy $p): Policy => is_string($p) ? $this->registry->get($p) : $p,
            $policies,
        );
    }
}
