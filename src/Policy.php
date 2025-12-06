<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter;

use InvalidArgumentException;
use JsonSerializable;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

use function array_map;
use function array_values;
use function class_exists;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;
use function throw_if;
use function throw_unless;

/**
 * Represents a named collection of access control rules.
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Policy implements JsonSerializable
{
    /**
     * @param array<Rule> $rules
     */
    private function __construct(
        private string $name,
        private string $description = '',
        private array $rules = [],
    ) {}

    /**
     * Create a new policy with the given name.
     */
    public static function create(string $name): self
    {
        return new self(name: $name);
    }

    /**
     * Create a policy from an array definition.
     *
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        // Validate required fields
        throw_if(!isset($data['name']) || !is_string($data['name']), InvalidArgumentException::class, 'Policy data must contain a "name" field of type string');

        $rules = [];

        if (isset($data['rules'])) {
            throw_unless(is_array($data['rules']), InvalidArgumentException::class, 'Policy "rules" must be an array');

            foreach ($data['rules'] as $ruleData) {
                throw_unless(is_array($ruleData), InvalidArgumentException::class, 'Each rule must be an array');

                // Validate rule structure
                throw_if(!isset($ruleData['path']) || !is_string($ruleData['path']), InvalidArgumentException::class, 'Each rule must have a "path" field of type string');

                /** @var array{path: string, effect?: string, capabilities?: array<string>, conditions?: array<string, mixed>, description?: string} $ruleData */
                $rules[] = Rule::fromArray($ruleData);
            }
        }

        return new self(
            name: $data['name'],
            description: isset($data['description']) && is_string($data['description']) ? $data['description'] : '',
            rules: $rules,
        );
    }

    /**
     * Load a policy from a YAML file.
     */
    public static function fromYaml(string $path): self
    {
        throw_unless(file_exists($path), RuntimeException::class, 'YAML file not found: '.$path);

        throw_unless(class_exists(Yaml::class), RuntimeException::class, 'symfony/yaml is required to load YAML files');

        $data = Yaml::parseFile($path);

        throw_unless(is_array($data), RuntimeException::class, 'Invalid YAML in file: '.$path);

        /** @var array{name: string, description?: string, rules?: array<array{path: string, effect?: string, capabilities?: array<string>, conditions?: array<string, mixed>, description?: string}>} $data */
        return self::fromArray($data);
    }

    /**
     * Load a policy from a JSON file.
     */
    public static function fromJson(string $path): self
    {
        throw_unless(file_exists($path), RuntimeException::class, 'JSON file not found: '.$path);

        $contents = file_get_contents($path);

        throw_if($contents === false, RuntimeException::class, 'Failed to read JSON file: '.$path);

        $data = json_decode($contents, true);

        throw_unless(is_array($data), RuntimeException::class, 'Invalid JSON in file: '.$path);

        /** @var array{name: string, description?: string, rules?: array<array{path: string, effect?: string, capabilities?: array<string>, conditions?: array<string, mixed>, description?: string}>} $data */
        return self::fromArray($data);
    }

    /**
     * Set the policy name.
     */
    public function name(string $name): self
    {
        return new self(
            name: $name,
            description: $this->description,
            rules: $this->rules,
        );
    }

    /**
     * Set the policy description.
     */
    public function description(string $description): self
    {
        return new self(
            name: $this->name,
            description: $description,
            rules: $this->rules,
        );
    }

    /**
     * Add a rule to the policy.
     */
    public function addRule(Rule $rule): self
    {
        return new self(
            name: $this->name,
            description: $this->description,
            rules: [...$this->rules, $rule],
        );
    }

    /**
     * Get the policy name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the policy description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get all rules in the policy.
     *
     * @return array<Rule>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Convert the policy to an array.
     *
     * @return array{
     *     name: string,
     *     description: string,
     *     rules: array<int, array{path: string, effect: string, capabilities: array<int, string>, conditions: array<string, mixed>, description: null|string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'rules' => array_values(array_map(
                static fn (Rule $rule): array => $rule->toArray(),
                $this->rules,
            )),
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     description: string,
     *     rules: array<int, array{path: string, effect: string, capabilities: array<int, string>, conditions: array<string, mixed>, description: null|string}>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
