<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter;

use Cline\Arbiter\Exception\EachPolicyRuleMustBeArrayException;
use Cline\Arbiter\Exception\FailedToReadJsonFileException;
use Cline\Arbiter\Exception\InvalidJsonException;
use Cline\Arbiter\Exception\InvalidYamlException;
use Cline\Arbiter\Exception\JsonFileNotFoundException;
use Cline\Arbiter\Exception\PolicyDataMissingNameFieldException;
use Cline\Arbiter\Exception\PolicyRuleMustHavePathFieldException;
use Cline\Arbiter\Exception\PolicyRulesMustBeArrayException;
use Cline\Arbiter\Exception\SymfonyYamlRequiredToLoadYamlFilesException;
use Cline\Arbiter\Exception\YamlFileNotFoundException;
use JsonSerializable;
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
 *
 * A policy groups related rules that define what paths and capabilities
 * are allowed or denied. Policies are immutable and can be loaded from
 * arrays, JSON files, or YAML files. Each policy has a unique name for
 * identification and registration.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Policy implements JsonSerializable
{
    /**
     * Create a new policy instance.
     *
     * @param string      $name        Unique identifier for the policy
     * @param string      $description Human-readable description of the policy's purpose
     * @param array<Rule> $rules       Collection of access control rules in this policy
     */
    private function __construct(
        private string $name,
        private string $description = '',
        private array $rules = [],
    ) {}

    /**
     * Create a new policy with the given name.
     *
     * @param string $name The unique name for this policy
     */
    public static function create(string $name): self
    {
        return new self(name: $name);
    }

    /**
     * Create a policy from an array definition.
     *
     * Expects array with 'name' key (required) and optional 'description' and 'rules' keys.
     * Each rule in the 'rules' array must have a 'path' key and optional 'effect',
     * 'capabilities', 'conditions', and 'description' keys.
     *
     * @param array<mixed> $data Array containing policy definition with name, description, and rules
     *
     * @throws EachPolicyRuleMustBeArrayException   If any rule in 'rules' is not an array
     * @throws PolicyDataMissingNameFieldException  If 'name' field is missing or not a string
     * @throws PolicyRuleMustHavePathFieldException If any rule is missing 'path' field
     * @throws PolicyRulesMustBeArrayException      If 'rules' field exists but is not an array
     */
    public static function fromArray(array $data): self
    {
        // Validate required fields
        throw_if(!isset($data['name']) || !is_string($data['name']), PolicyDataMissingNameFieldException::create());

        $rules = [];

        if (isset($data['rules'])) {
            throw_unless(is_array($data['rules']), PolicyRulesMustBeArrayException::create());

            foreach ($data['rules'] as $ruleData) {
                throw_unless(is_array($ruleData), EachPolicyRuleMustBeArrayException::create());

                // Validate rule structure
                throw_if(!isset($ruleData['path']) || !is_string($ruleData['path']), PolicyRuleMustHavePathFieldException::create());

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
     *
     * Requires symfony/yaml package to be installed. The YAML file should
     * contain a policy definition with the same structure as fromArray().
     *
     * @param string $path Absolute path to the YAML policy file
     *
     * @throws InvalidYamlException                        If the YAML file cannot be parsed or contains invalid data
     * @throws SymfonyYamlRequiredToLoadYamlFilesException If symfony/yaml is not installed
     * @throws YamlFileNotFoundException                   If the file does not exist
     */
    public static function fromYaml(string $path): self
    {
        throw_unless(file_exists($path), YamlFileNotFoundException::atPath($path));

        throw_unless(class_exists(Yaml::class), SymfonyYamlRequiredToLoadYamlFilesException::create());

        $data = Yaml::parseFile($path);

        throw_unless(is_array($data), InvalidYamlException::inFile($path));

        /** @var array{name: string, description?: string, rules?: array<array{path: string, effect?: string, capabilities?: array<string>, conditions?: array<string, mixed>, description?: string}>} $data */
        return self::fromArray($data);
    }

    /**
     * Load a policy from a JSON file.
     *
     * The JSON file should contain a policy definition with the same
     * structure as fromArray() expects.
     *
     * @param string $path Absolute path to the JSON policy file
     *
     * @throws FailedToReadJsonFileException If the file cannot be read
     * @throws InvalidJsonException          If the file contains invalid JSON or non-array data
     * @throws JsonFileNotFoundException     If the file does not exist
     */
    public static function fromJson(string $path): self
    {
        throw_unless(file_exists($path), JsonFileNotFoundException::atPath($path));

        $contents = file_get_contents($path);

        throw_if($contents === false, FailedToReadJsonFileException::atPath($path));

        $data = json_decode($contents, true);

        throw_unless(is_array($data), InvalidJsonException::inFile($path));

        /** @var array{name: string, description?: string, rules?: array<array{path: string, effect?: string, capabilities?: array<string>, conditions?: array<string, mixed>, description?: string}>} $data */
        return self::fromArray($data);
    }

    /**
     * Create a new policy instance with a different name.
     *
     * @param  string $name The new name for the policy
     * @return self   A new policy instance with the updated name
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
     * Create a new policy instance with a different description.
     *
     * @param  string $description The new description for the policy
     * @return self   A new policy instance with the updated description
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
     * Create a new policy instance with an additional rule.
     *
     * @param  Rule $rule The rule to add to the policy
     * @return self A new policy instance containing the additional rule
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
     * Get the unique name of this policy.
     *
     * @return string The policy name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the description of this policy.
     *
     * @return string The policy description (empty string if not set)
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get all rules defined in this policy.
     *
     * @return array<Rule> Array of Rule instances
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Convert the policy to an array representation.
     *
     * Serializes the policy and all its rules to a plain array structure
     * suitable for JSON encoding or storage.
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
     * Serialize the policy for JSON encoding.
     *
     * Implements JsonSerializable interface to provide custom JSON representation.
     * Delegates to toArray() for the actual serialization logic.
     *
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
