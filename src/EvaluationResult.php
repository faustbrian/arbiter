<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter;

/**
 * Represents the result of a policy evaluation.
 *
 * Contains information about whether the action was allowed or denied,
 * which rule and policy matched, and the reason for the decision.
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class EvaluationResult
{
    /**
     * @param array<Policy> $evaluatedPolicies
     */
    public function __construct(
        private bool $allowed,
        private bool $explicitDeny,
        private ?Rule $matchedRule,
        private ?Policy $matchedPolicy,
        private string $reason,
        private array $evaluatedPolicies,
    ) {}

    /**
     * Create an allowed result.
     *
     * @param array<Policy> $evaluated
     */
    public static function allowed(Rule $rule, Policy $policy, array $evaluated): self
    {
        return new self(
            allowed: true,
            explicitDeny: false,
            matchedRule: $rule,
            matchedPolicy: $policy,
            reason: 'Allowed by rule',
            evaluatedPolicies: $evaluated,
        );
    }

    /**
     * Create a denied result (no matching allow rule).
     *
     * @param array<Policy> $evaluated
     */
    public static function denied(string $reason, array $evaluated): self
    {
        return new self(
            allowed: false,
            explicitDeny: false,
            matchedRule: null,
            matchedPolicy: null,
            reason: $reason,
            evaluatedPolicies: $evaluated,
        );
    }

    /**
     * Create an explicitly denied result (matched a deny rule).
     *
     * @param array<Policy> $evaluated
     */
    public static function explicitlyDenied(Rule $rule, Policy $policy, array $evaluated): self
    {
        return new self(
            allowed: false,
            explicitDeny: true,
            matchedRule: $rule,
            matchedPolicy: $policy,
            reason: 'Explicitly denied by rule',
            evaluatedPolicies: $evaluated,
        );
    }

    /**
     * Check if the action is allowed.
     */
    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    /**
     * Check if the action is denied.
     */
    public function isDenied(): bool
    {
        return !$this->allowed;
    }

    /**
     * Check if the action was explicitly denied by a deny rule.
     */
    public function isExplicitDeny(): bool
    {
        return $this->explicitDeny;
    }

    /**
     * Get the rule that matched, if any.
     */
    public function getMatchedRule(): ?Rule
    {
        return $this->matchedRule;
    }

    /**
     * Get the policy that matched, if any.
     */
    public function getMatchedPolicy(): ?Policy
    {
        return $this->matchedPolicy;
    }

    /**
     * Get the reason for the decision.
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * Get all policies that were evaluated.
     *
     * @return array<Policy>
     */
    public function getEvaluatedPolicies(): array
    {
        return $this->evaluatedPolicies;
    }
}
