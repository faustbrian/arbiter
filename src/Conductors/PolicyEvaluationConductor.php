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
use Cline\Arbiter\Exception\CapabilityMustBeSetException;
use Cline\Arbiter\Exception\PathAndCapabilityMustBeSetException;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Services\EvaluationService;

use function array_merge;
use function assert;
use function throw_if;

/**
 * Fluent conductor for policy-centric evaluation workflow.
 *
 * Provides a policy-first evaluation approach where you start with one or more
 * policies and then specify the path and capability to check. Uses immutable
 * builder pattern to construct the evaluation context through method chaining.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class PolicyEvaluationConductor
{
    /**
     * Create a new policy evaluation conductor.
     *
     * @param array<Policy>        $policies   Array of policies to evaluate
     * @param EvaluationService    $evaluator  Service responsible for executing policy evaluation logic
     * @param null|string          $path       Resource path to evaluate (set via can() method)
     * @param null|Capability      $capability Capability to check (set via can() method)
     * @param array<string, mixed> $context    Additional context data for condition evaluation
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
     *
     * Configures the resource path and capability to evaluate against the
     * configured policies. This is the key method for specifying what operation
     * to authorize.
     *
     * @param string     $path       Resource path to check (e.g., 'users/123', 'projects/*')
     * @param Capability $capability Capability to evaluate (defaults to Read for convenience)
     *
     * @return self New conductor instance with path and capability configured
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
            policies: $this->policies,
            evaluator: $this->evaluator,
            path: $this->path,
            capability: $this->capability,
            context: array_merge($this->context, $context),
        );
    }

    /**
     * Check if access is allowed.
     *
     * Evaluates the configured policies and returns true if the capability is
     * granted for the specified path and context. This is the primary method
     * for yes/no authorization decisions.
     *
     * @throws PathAndCapabilityMustBeSetException When path or capability not set via can()
     * @return bool                                True if access is allowed, false otherwise
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
     *
     * Inverse of allowed() - returns true when access is not granted.
     * Useful for guard clauses and negative authorization checks.
     *
     * @throws PathAndCapabilityMustBeSetException When path or capability not set via can()
     * @return bool                                True if access is denied, false if allowed
     */
    public function denied(): bool
    {
        return !$this->allowed();
    }

    /**
     * Get the full evaluation result with detailed information.
     *
     * Returns the complete evaluation result including matched rules, specificity
     * scores, and other metadata. Useful when you need more than a simple boolean
     * answer about authorization.
     *
     * @throws PathAndCapabilityMustBeSetException When path or capability not set via can()
     * @return EvaluationResult                    Complete evaluation result with decision details
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
     * Returns a list of resource paths that the configured policies grant access
     * to for the current capability. Useful for generating navigation menus or
     * filtering resource lists based on authorization.
     *
     * @throws CapabilityMustBeSetException When capability not set via can()
     * @return array<string>                Array of accessible resource paths
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

    /**
     * Ensure path and capability are set before evaluation.
     *
     * @throws PathAndCapabilityMustBeSetException When either path or capability is not set
     */
    private function ensureComplete(): void
    {
        throw_if($this->path === null || !$this->capability instanceof Capability, PathAndCapabilityMustBeSetException::beforeEvaluation());
    }

    /**
     * Ensure capability is set before listing paths.
     *
     * @throws CapabilityMustBeSetException When capability is not set
     */
    private function ensureCapability(): void
    {
        throw_if(!$this->capability instanceof Capability, CapabilityMustBeSetException::beforeListingPaths());
    }
}
