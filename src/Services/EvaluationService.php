<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Services;

use Cline\Arbiter\Capability;
use Cline\Arbiter\Effect;
use Cline\Arbiter\EvaluationResult;
use Cline\Arbiter\Policy;

use function array_unique;
use function array_values;
use function usort;

/**
 * Evaluates access control policies to determine if actions are allowed or denied.
 *
 * Implements the core policy evaluation logic including rule matching, specificity
 * calculation, and explicit deny precedence. Deny rules always take precedence over
 * allow rules regardless of specificity.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class EvaluationService
{
    /**
     * Create a new evaluation service.
     *
     * @param SpecificityCalculator $specificityCalculator Service for calculating rule specificity scores
     */
    public function __construct(
        private SpecificityCalculator $specificityCalculator,
    ) {}

    /**
     * Evaluate policies to determine if access should be allowed or denied.
     *
     * Collects all matching rules from all policies, sorts by specificity (most specific first),
     * and applies the evaluation logic:
     * 1. No matching rules = implicit deny
     * 2. Any deny rule = explicit deny (deny always wins)
     * 3. First allow rule = allowed
     *
     * @param  array<Policy>        $policies   Policies to evaluate
     * @param  Capability           $capability Capability being requested
     * @param  string               $path       Path being accessed
     * @param  array<string, mixed> $context    Context data for variable resolution and condition evaluation
     * @return EvaluationResult     Result indicating allowed or denied with details
     */
    public function evaluate(
        array $policies,
        Capability $capability,
        string $path,
        array $context = [],
    ): EvaluationResult {
        // Collect all matching rules from all policies
        $matchingRules = [];

        foreach ($policies as $policy) {
            foreach ($policy->getRules() as $rule) {
                if (!$rule->matchesPath($path, $context)) {
                    continue;
                }

                if (!$rule->conditionsSatisfied($context)) {
                    continue;
                }

                // Deny rules don't need capability check
                if ($rule->getEffect() === Effect::Deny) {
                    $matchingRules[] = [
                        'rule' => $rule,
                        'policy' => $policy,
                        'specificity' => $this->specificityCalculator->calculate($rule->getPath()),
                    ];

                    continue;
                }

                // Allow rules need capability check
                if (!$rule->hasCapability($capability)) {
                    continue;
                }

                $matchingRules[] = [
                    'rule' => $rule,
                    'policy' => $policy,
                    'specificity' => $this->specificityCalculator->calculate($rule->getPath()),
                ];
            }
        }

        // No matching rules = implicit deny
        if ($matchingRules === []) {
            return EvaluationResult::denied(
                'No matching rule found',
                $policies,
            );
        }

        // Sort by specificity (most specific first)
        usort($matchingRules, static fn (array $a, array $b): int => $b['specificity'] <=> $a['specificity']);

        // Check for explicit deny (deny always wins)
        foreach ($matchingRules as $match) {
            if ($match['rule']->getEffect() === Effect::Deny) {
                return EvaluationResult::explicitlyDenied(
                    $match['rule'],
                    $match['policy'],
                    $policies,
                );
            }
        }

        // First allow rule wins
        $match = $matchingRules[0];

        return EvaluationResult::allowed(
            $match['rule'],
            $match['policy'],
            $policies,
        );
    }

    /**
     * List all paths that allow the specified capability.
     *
     * Scans all allow rules in all policies and returns the unique set of path
     * patterns that grant the requested capability. Excludes deny rules and rules
     * for other capabilities.
     *
     * @param  array<Policy> $policies   Policies to scan for accessible paths
     * @param  Capability    $capability Capability to find paths for
     * @return array<string> Unique array of path patterns that allow this capability
     */
    public function listAccessiblePaths(array $policies, Capability $capability): array
    {
        $paths = [];

        foreach ($policies as $policy) {
            foreach ($policy->getRules() as $rule) {
                if ($rule->getEffect() !== Effect::Allow) {
                    continue;
                }

                if (!$rule->hasCapability($capability)) {
                    continue;
                }

                $paths[] = $rule->getPath();
            }
        }

        return array_unique($paths);
    }

    /**
     * Get all capabilities available at a specific path.
     *
     * Evaluates all policies to determine which capabilities are granted at the
     * specified path. Only returns capabilities from allow rules that match the
     * path and satisfy their conditions. Deny rules are excluded.
     *
     * @param  array<Policy>        $policies Policies to evaluate
     * @param  string               $path     The path to check capabilities for
     * @param  array<string, mixed> $context  Context for path matching and condition evaluation
     * @return array<Capability>    Unique array of capabilities available at this path
     */
    public function getCapabilities(array $policies, string $path, array $context = []): array
    {
        $capabilities = [];

        foreach ($policies as $policy) {
            foreach ($policy->getRules() as $rule) {
                if (!$rule->matchesPath($path, $context)) {
                    continue;
                }

                if (!$rule->conditionsSatisfied($context)) {
                    continue;
                }

                if ($rule->getEffect() !== Effect::Allow) {
                    continue;
                }

                foreach ($rule->getCapabilities() as $cap) {
                    $capabilities[$cap->value] = $cap;
                }
            }
        }

        return array_values($capabilities);
    }
}
