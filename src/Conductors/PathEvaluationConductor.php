<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Conductors;

use Cline\Arbiter\Capability;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Services\EvaluationService;
use Cline\Arbiter\Services\PolicyRegistry;
use LogicException;

use function array_map;
use function array_merge;
use function is_string;
use function throw_if;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class PathEvaluationConductor
{
    /**
     * @param array<Policy>        $policies
     * @param array<string, mixed> $context
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
     * @param array<Policy|string>|string $policies
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
     * @param array<string, mixed> $context
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
     * @return array<Capability>
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
     */
    public function denies(Capability $capability): bool
    {
        return !$this->allows($capability);
    }

    private function ensurePolicies(): void
    {
        throw_if($this->policies === [], LogicException::class, 'Policies must be set via against() before evaluation.');
    }

    /**
     * Resolve policy names/objects to Policy instances.
     *
     * @param  array<Policy|string>|string $policies
     * @return array<Policy>
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
