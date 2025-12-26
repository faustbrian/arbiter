<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter;

/**
 * Represents the result of a policy-based authorization evaluation.
 *
 * Contains comprehensive information about the authorization decision including
 * whether the action was allowed or denied, which specific rule and policy matched,
 * the reason for the decision, and the complete list of policies that were evaluated
 * during the authorization check.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class EvaluationResult
{
    /**
     * Create a new evaluation result instance.
     *
     * @param bool          $allowed           Whether the action was authorized to proceed
     * @param bool          $explicitDeny      Whether the denial was due to an explicit Deny rule
     *                                         (as opposed to implicit denial from no matching Allow rule)
     * @param null|Rule     $matchedRule       The specific rule that determined the outcome,
     *                                         or null if no rule matched (implicit denial)
     * @param null|Policy   $matchedPolicy     The policy containing the matched rule,
     *                                         or null if no rule matched
     * @param string        $reason            Human-readable explanation for why the action was
     *                                         allowed or denied, used for logging and debugging
     * @param array<Policy> $evaluatedPolicies Complete list of all policies that were
     *                                         evaluated during this authorization check,
     *                                         useful for auditing and debugging
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
     * Create an allowed result for a successfully authorized action.
     *
     * @param  Rule          $rule      The rule that granted authorization
     * @param  Policy        $policy    The policy containing the allowing rule
     * @param  array<Policy> $evaluated All policies evaluated during the check
     * @return self          Result indicating the action is allowed
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
     * Create a denied result due to no matching allow rule (implicit denial).
     *
     * @param  string        $reason    Human-readable explanation for the denial
     * @param  array<Policy> $evaluated All policies evaluated during the check
     * @return self          Result indicating implicit denial
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
     * Create an explicitly denied result when a Deny rule is matched.
     *
     * @param  Rule          $rule      The deny rule that was matched
     * @param  Policy        $policy    The policy containing the denying rule
     * @param  array<Policy> $evaluated All policies evaluated during the check
     * @return self          Result indicating explicit denial
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
     * Check if the action was authorized.
     *
     * @return bool True if the action is allowed, false otherwise
     */
    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    /**
     * Check if the action was denied.
     *
     * @return bool True if the action is denied, false otherwise
     */
    public function isDenied(): bool
    {
        return !$this->allowed;
    }

    /**
     * Check if the action was explicitly denied by a Deny rule.
     *
     * Distinguishes between explicit denial (a Deny rule matched) and
     * implicit denial (no Allow rule matched).
     *
     * @return bool True if explicitly denied by a Deny rule, false otherwise
     */
    public function isExplicitDeny(): bool
    {
        return $this->explicitDeny;
    }

    /**
     * Get the rule that determined the authorization outcome.
     *
     * @return null|Rule The matched rule, or null for implicit denial
     */
    public function getMatchedRule(): ?Rule
    {
        return $this->matchedRule;
    }

    /**
     * Get the policy containing the matched rule.
     *
     * @return null|Policy The matched policy, or null for implicit denial
     */
    public function getMatchedPolicy(): ?Policy
    {
        return $this->matchedPolicy;
    }

    /**
     * Get the human-readable reason for the authorization decision.
     *
     * @return string Explanation of why the action was allowed or denied
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * Get all policies that were evaluated during the authorization check.
     *
     * @return array<Policy> List of evaluated policies
     */
    public function getEvaluatedPolicies(): array
    {
        return $this->evaluatedPolicies;
    }
}
