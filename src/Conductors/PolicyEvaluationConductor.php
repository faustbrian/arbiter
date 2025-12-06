<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Conductors;

use Cline\Arbiter\Capability;
use Cline\Arbiter\EvaluationResult;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Services\EvaluationService;
use LogicException;

use function array_merge;
use function assert;
use function throw_if;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class PolicyEvaluationConductor
{
    /**
     * @param array<Policy>        $policies
     * @param array<string, mixed> $context
     */
    public function __construct(
        private array $policies,
        private EvaluationService $evaluator,
        private ?string $path = null,
        private ?Capability $capability = null,
        private array $context = [],
    ) {}

    /**
     * Set the path and capability to check.
     */
    public function can(string $path, Capability $capability = Capability::Read): self
    {
        return new self(
            policies: $this->policies,
            evaluator: $this->evaluator,
            path: $path,
            capability: $capability,
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
            policies: $this->policies,
            evaluator: $this->evaluator,
            path: $this->path,
            capability: $this->capability,
            context: array_merge($this->context, $context),
        );
    }

    /**
     * Check if access is allowed.
     */
    public function allowed(): bool
    {
        $this->ensureComplete();

        assert($this->path !== null && $this->capability instanceof Capability);

        return $this->evaluator->evaluate(
            policies: $this->policies,
            capability: $this->capability,
            path: $this->path,
            context: $this->context,
        )->isAllowed();
    }

    /**
     * Check if access is denied.
     */
    public function denied(): bool
    {
        return !$this->allowed();
    }

    /**
     * Get the full evaluation result.
     */
    public function evaluate(): EvaluationResult
    {
        $this->ensureComplete();

        assert($this->path !== null && $this->capability instanceof Capability);

        return $this->evaluator->evaluate(
            policies: $this->policies,
            capability: $this->capability,
            path: $this->path,
            context: $this->context,
        );
    }

    /**
     * Get all accessible paths for the current capability.
     *
     * @return array<string>
     */
    public function accessiblePaths(): array
    {
        $this->ensureCapability();

        assert($this->capability instanceof Capability);

        return $this->evaluator->listAccessiblePaths(
            policies: $this->policies,
            capability: $this->capability,
        );
    }

    private function ensureComplete(): void
    {
        throw_if($this->path === null || !$this->capability instanceof Capability, LogicException::class, 'Path and capability must be set before evaluation. Use can($path, $capability) first.');
    }

    private function ensureCapability(): void
    {
        throw_if(!$this->capability instanceof Capability, LogicException::class, 'Capability must be set before listing accessible paths. Use can($path, $capability) first.');
    }
}
