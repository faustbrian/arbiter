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
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ArbiterManager
{
    public function __construct(
        private PolicyRegistry $registry,
        private EvaluationService $evaluator,
    ) {}

    /**
     * Create a policy evaluation conductor for the given policy.
     *
     * @param array<Policy|string>|Policy|string $policy
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
     * Set the policy repository.
     */
    public function repository(PolicyRepositoryInterface $repository): self
    {
        $this->registry->setRepository($repository);

        return $this;
    }

    /**
     * Register a policy.
     */
    public function register(Policy $policy): void
    {
        $this->registry->add($policy);
    }

    /**
     * Check if a policy exists.
     */
    public function has(string $name): bool
    {
        return $this->registry->has($name);
    }

    /**
     * Get a policy by name.
     */
    public function get(string $name): Policy
    {
        return $this->registry->get($name);
    }

    /**
     * Get all registered policies.
     *
     * @return array<Policy>
     */
    public function all(): array
    {
        return $this->registry->all();
    }

    /**
     * Resolve policy names/objects to Policy instances.
     *
     * @param  array<Policy|string>|Policy|string $policy
     * @return array<Policy>
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
