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
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class EvaluationService
{
    public function __construct(
        private SpecificityCalculator $specificityCalculator,
    ) {}

    /**
     * Evaluate policies against a path and capability.
     *
     * @param array<Policy>        $policies
     * @param array<string, mixed> $context
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
     * List all accessible paths for a capability.
     *
     * @param  array<Policy> $policies
     * @return array<string>
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
     * @param  array<Policy>        $policies
     * @param  array<string, mixed> $context
     * @return array<Capability>
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
