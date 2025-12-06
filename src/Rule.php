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
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Rule implements JsonSerializable
{
    /**
     * Create a new rule instance.
     *
     * @param array<Capability>    $capabilities
     * @param array<string, mixed> $conditions
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
     * @param array{
     *     path: string,
     *     effect?: string,
     *     capabilities?: array<string>,
     *     conditions?: array<string, mixed>,
     *     description?: string
     * } $data
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
     * Set the capabilities allowed/denied by this rule.
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
     * @param mixed $value Expected value (string/int), array of values, or callable
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
     * Add a description for this rule.
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
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the effect (allow/deny) for this rule.
     */
    public function getEffect(): Effect
    {
        return $this->effect;
    }

    /**
     * Get all capabilities for this rule.
     *
     * @return array<Capability>
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Get all conditions for this rule.
     *
     * @return array<string, mixed>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Get the description for this rule.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Check if the rule's path pattern matches the given path.
     *
     * @param array<string, mixed> $context
     */
    public function matchesPath(string $path, array $context = []): bool
    {
        $matcher = $this->pathMatcher ?? new PathMatcher();

        return $matcher->matches($this->path, $path, $context);
    }

    /**
     * Check if the rule has the given capability.
     * Admin capability implies all others.
     */
    public function hasCapability(Capability $capability): bool
    {
        return array_any($this->capabilities, fn ($ruleCapability): bool => $ruleCapability->implies($capability));
    }

    /**
     * Check if all conditions are satisfied with the given context.
     *
     * @param array<string, mixed> $context
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
     * Convert the rule to an array.
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
