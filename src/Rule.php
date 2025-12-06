<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter;

use Cline\Arbiter\Condition\ConditionEvaluator;
use Cline\Arbiter\Path\PathMatcher;
use JsonSerializable;

use function array_any;
use function array_map;
use function array_values;

/**
 * Represents a single access control rule with path pattern, effect, capabilities, and conditions.
 *
 * Rules define granular access control by combining:
 * - A path pattern (with wildcard and variable support)
 * - An effect (allow or deny)
 * - Capabilities that are granted/denied
 * - Optional conditions that must be satisfied
 *
 * Rules are immutable and evaluated by the EvaluationService during access checks.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Rule implements JsonSerializable
{
    /**
     * Create a new rule instance.
     *
     * @param string                  $path               Path pattern to match (supports wildcards and variables)
     * @param Effect                  $effect             Whether to allow or deny access
     * @param array<Capability>       $capabilities       Capabilities granted or denied by this rule
     * @param array<string, mixed>    $conditions         Conditions that must be satisfied for rule to apply
     * @param null|string             $description        Optional human-readable description of the rule
     * @param null|PathMatcher        $pathMatcher        Optional custom path matcher for testing
     * @param null|ConditionEvaluator $conditionEvaluator Optional custom condition evaluator for testing
     */
    private function __construct(
        private string $path,
        private Effect $effect,
        private array $capabilities = [],
        private array $conditions = [],
        private ?string $description = null,
        private ?PathMatcher $pathMatcher = null,
        private ?ConditionEvaluator $conditionEvaluator = null,
    ) {}

    /**
     * Create an allow rule for the given path pattern.
     *
     * @param  string $path The path pattern this rule applies to
     * @return self   A new rule with Allow effect
     */
    public static function allow(string $path): self
    {
        return new self(
            path: $path,
            effect: Effect::Allow,
        );
    }

    /**
     * Create a deny rule for the given path pattern.
     *
     * @param  string $path The path pattern this rule denies
     * @return self   A new rule with Deny effect
     */
    public static function deny(string $path): self
    {
        return new self(
            path: $path,
            effect: Effect::Deny,
        );
    }

    /**
     * Create a rule from an array definition.
     *
     * Expects array with 'path' key (required) and optional 'effect', 'capabilities',
     * 'conditions', and 'description' keys. Effect defaults to 'allow' if not specified.
     *
     * @param array{
     *     path: string,
     *     effect?: string,
     *     capabilities?: array<string>,
     *     conditions?: array<string, mixed>,
     *     description?: string
     * } $data Array containing rule definition
     */
    public static function fromArray(array $data): self
    {
        $effect = isset($data['effect'])
            ? Effect::from($data['effect'])
            : Effect::Allow;

        $capabilities = [];

        if (isset($data['capabilities'])) {
            foreach ($data['capabilities'] as $cap) {
                $capabilities[] = Capability::fromString($cap);
            }
        }

        return new self(
            path: $data['path'],
            effect: $effect,
            capabilities: $capabilities,
            conditions: $data['conditions'] ?? [],
            description: $data['description'] ?? null,
        );
    }

    /**
     * Create a new rule instance with the specified capabilities.
     *
     * @param  Capability ...$capabilities One or more capabilities to allow/deny
     * @return self       A new rule instance with the updated capabilities
     */
    public function capabilities(Capability ...$capabilities): self
    {
        return new self(
            path: $this->path,
            effect: $this->effect,
            capabilities: $capabilities,
            conditions: $this->conditions,
            description: $this->description,
            pathMatcher: $this->pathMatcher,
            conditionEvaluator: $this->conditionEvaluator,
        );
    }

    /**
     * Add a condition that must be satisfied for this rule to apply.
     *
     * Conditions are evaluated against the context during rule matching.
     * The value can be a literal for exact match, an array for "in" comparison,
     * or a callable for custom validation logic.
     *
     * @param  string $key   The condition key to check in the context
     * @param  mixed  $value Expected value (string/int for exact match, array for "in" check, or callable)
     * @return self   A new rule instance with the additional condition
     */
    public function when(string $key, mixed $value): self
    {
        return new self(
            path: $this->path,
            effect: $this->effect,
            capabilities: $this->capabilities,
            conditions: [...$this->conditions, $key => $value],
            description: $this->description,
            pathMatcher: $this->pathMatcher,
            conditionEvaluator: $this->conditionEvaluator,
        );
    }

    /**
     * Create a new rule instance with a description.
     *
     * @param  string $description Human-readable description of the rule's purpose
     * @return self   A new rule instance with the updated description
     */
    public function description(string $description): self
    {
        return new self(
            path: $this->path,
            effect: $this->effect,
            capabilities: $this->capabilities,
            conditions: $this->conditions,
            description: $description,
            pathMatcher: $this->pathMatcher,
            conditionEvaluator: $this->conditionEvaluator,
        );
    }

    /**
     * Get the path pattern for this rule.
     *
     * @return string The path pattern (may include wildcards and variables)
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the effect of this rule.
     *
     * @return Effect The effect (Allow or Deny)
     */
    public function getEffect(): Effect
    {
        return $this->effect;
    }

    /**
     * Get all capabilities defined in this rule.
     *
     * @return array<Capability> Array of Capability instances
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Get all conditions for this rule.
     *
     * @return array<string, mixed> Associative array of condition keys to values
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Get the description of this rule.
     *
     * @return null|string The description, or null if not set
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Check if the rule's path pattern matches the given path.
     *
     * Uses PathMatcher to test if the actual path matches this rule's pattern,
     * including wildcard and variable resolution using the provided context.
     *
     * @param  string               $path    The actual path to test
     * @param  array<string, mixed> $context Variable substitution context
     * @return bool                 True if the path matches the pattern
     */
    public function matchesPath(string $path, array $context = []): bool
    {
        $matcher = $this->pathMatcher ?? new PathMatcher();

        return $matcher->matches($this->path, $path, $context);
    }

    /**
     * Check if the rule grants or denies the specified capability.
     *
     * Uses capability implication logic: Admin capability implies all others,
     * so a rule with Admin will match any capability check.
     *
     * @param  Capability $capability The capability to check for
     * @return bool       True if the rule has this capability (or implies it)
     */
    public function hasCapability(Capability $capability): bool
    {
        return array_any($this->capabilities, fn ($ruleCapability): bool => $ruleCapability->implies($capability));
    }

    /**
     * Check if all conditions are satisfied with the given context.
     *
     * Returns true if no conditions are defined (unconditional rule).
     * Otherwise, delegates to ConditionEvaluator to check all conditions
     * against the provided context.
     *
     * @param  array<string, mixed> $context Context data for condition evaluation
     * @return bool                 True if all conditions are satisfied or no conditions exist
     */
    public function conditionsSatisfied(array $context): bool
    {
        if ($this->conditions === []) {
            return true;
        }

        $evaluator = $this->conditionEvaluator ?? new ConditionEvaluator();

        return $evaluator->evaluateAll($this->conditions, $context);
    }

    /**
     * Convert the rule to an array representation.
     *
     * Serializes the rule to a plain array structure suitable for JSON encoding,
     * storage, or transmission. Capabilities are converted to their string values.
     *
     * @return array{
     *     path: string,
     *     effect: string,
     *     capabilities: array<int, string>,
     *     conditions: array<string, mixed>,
     *     description: null|string
     * }
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'effect' => $this->effect->value,
            'capabilities' => array_values(array_map(
                static fn (Capability $cap): string => $cap->value,
                $this->capabilities,
            )),
            'conditions' => $this->conditions,
            'description' => $this->description,
        ];
    }

    /**
     * Serialize the rule for JSON encoding.
     *
     * Implements JsonSerializable interface to provide custom JSON representation.
     * Delegates to toArray() for the actual serialization logic.
     *
     * @return array{
     *     path: string,
     *     effect: string,
     *     capabilities: array<int, string>,
     *     conditions: array<string, mixed>,
     *     description: null|string
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
