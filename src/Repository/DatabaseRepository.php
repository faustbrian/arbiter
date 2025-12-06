<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Repository;

use Cline\Arbiter\Exception\InvalidDefinitionTypeException;
use Cline\Arbiter\Exception\InvalidJsonDefinitionException;
use Cline\Arbiter\Exception\InvalidParsedDefinitionException;
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;
use JsonException;
use PDO;

use const JSON_THROW_ON_ERROR;

use function array_fill;
use function array_flip;
use function array_intersect_key;
use function array_keys;
use function array_merge;
use function array_values;
use function assert;
use function count;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function sprintf;

/**
 * Load policy definitions from a database table.
 *
 * Uses PDO to query a database table containing policy definitions.
 * The definition column should contain JSON-encoded policy definitions.
 * @author Brian Faust <brian@cline.sh>
 */
final class DatabaseRepository implements PolicyRepositoryInterface
{
    /** @var null|array<string, Policy> */
    private ?array $cachedPolicies = null;

    /**
     * @param PDO                  $pdo              Database connection
     * @param string               $table            Table name containing policy definitions
     * @param string               $nameColumn       Column name for policy name
     * @param string               $definitionColumn Column name for policy definition (JSON)
     * @param array<string, mixed> $conditions       Additional WHERE conditions as key-value pairs
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table = 'policies',
        private readonly string $nameColumn = 'name',
        private readonly string $definitionColumn = 'definition',
        private readonly array $conditions = [],
    ) {}

    /**
     * Get a policy by name.
     *
     * @param  string                  $name The policy name
     * @throws PolicyNotFoundException If the policy is not found
     * @return Policy                  The policy instance
     */
    public function get(string $name): Policy
    {
        $sql = $this->buildQuery([$this->nameColumn => $name]);

        $stmt = $this->pdo->prepare($sql);
        $params = $this->buildParameters([$this->nameColumn => $name]);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw PolicyNotFoundException::forName($name);
        }

        assert(is_array($row));
        assert(isset($row[$this->definitionColumn]));

        return $this->parseDefinition($row[$this->definitionColumn], $name);
    }

    /**
     * Check if a policy exists.
     *
     * @param  string $name The policy name
     * @return bool   True if the policy exists, false otherwise
     */
    public function has(string $name): bool
    {
        $sql = $this->buildQuery([$this->nameColumn => $name], true);

        $stmt = $this->pdo->prepare($sql);
        $params = $this->buildParameters([$this->nameColumn => $name]);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get all policies.
     *
     * @return array<string, Policy> Map of policy names to instances
     */
    public function all(): array
    {
        if ($this->cachedPolicies !== null) {
            return $this->cachedPolicies;
        }

        $sql = $this->buildQuery([]);

        $stmt = $this->pdo->prepare($sql);
        $params = $this->buildParameters([]);
        $stmt->execute($params);

        /** @var array<string, Policy> $policies */
        $policies = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            assert(is_array($row));
            assert(isset($row[$this->nameColumn]));
            assert(is_string($row[$this->nameColumn]));
            assert(isset($row[$this->definitionColumn]));

            $name = $row[$this->nameColumn];
            $policies[$name] = $this->parseDefinition($row[$this->definitionColumn], $name);
        }

        $this->cachedPolicies = $policies;

        return $policies;
    }

    /**
     * Get multiple policies.
     *
     * @param  array<string>         $names The policy names to retrieve
     * @return array<string, Policy> Map of policy names to instances
     */
    public function getMany(array $names): array
    {
        if ($names === []) {
            return [];
        }

        // Use all() and filter if we've already cached all policies
        if ($this->cachedPolicies !== null) {
            return array_intersect_key($this->cachedPolicies, array_flip($names));
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $sql = $this->buildQuery([], false, sprintf('AND %s IN (%s)', $this->nameColumn, $placeholders));

        $stmt = $this->pdo->prepare($sql);
        $params = array_merge($this->buildParameters([]), $names);
        $stmt->execute($params);

        /** @var array<string, Policy> $policies */
        $policies = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            assert(is_array($row));
            assert(isset($row[$this->nameColumn]));
            assert(is_string($row[$this->nameColumn]));
            assert(isset($row[$this->definitionColumn]));

            $name = $row[$this->nameColumn];
            $policies[$name] = $this->parseDefinition($row[$this->definitionColumn], $name);
        }

        return $policies;
    }

    /**
     * Build SQL query.
     *
     * @param  array<string, mixed> $additionalConditions Additional WHERE conditions
     * @param  bool                 $count                Whether to build a COUNT query
     * @param  string               $suffix               Additional SQL suffix
     * @return string               The SQL query
     */
    private function buildQuery(array $additionalConditions = [], bool $count = false, string $suffix = ''): string
    {
        $select = $count ? 'COUNT(*)' : '*';
        $sql = sprintf('SELECT %s FROM %s', $select, $this->table);

        $conditions = array_merge($this->conditions, $additionalConditions);

        if ($conditions !== []) {
            $whereClauses = [];

            foreach (array_keys($conditions) as $column) {
                $whereClauses[] = $column.' = ?';
            }

            $sql .= ' WHERE '.implode(' AND ', $whereClauses);
        }

        if ($suffix !== '') {
            if ($conditions === []) {
                $sql .= ' WHERE 1=1';
            }

            $sql .= ' '.$suffix;
        }

        return $sql;
    }

    /**
     * Build parameter array for prepared statement.
     *
     * @param  array<string, mixed> $additionalConditions Additional WHERE conditions
     * @return array<mixed>         Array of parameter values
     */
    private function buildParameters(array $additionalConditions = []): array
    {
        $conditions = array_merge($this->conditions, $additionalConditions);

        return array_values($conditions);
    }

    /**
     * Parse a JSON definition from the database.
     *
     * @param  mixed                            $definition The raw definition value from the database
     * @param  string                           $name       The policy name (for error messages)
     * @throws InvalidDefinitionTypeException   If the definition is not a string or array
     * @throws InvalidJsonDefinitionException   If the JSON cannot be parsed
     * @throws InvalidParsedDefinitionException If the parsed JSON is not an array
     * @return Policy                           The policy instance
     */
    private function parseDefinition(mixed $definition, string $name): Policy
    {
        if (is_array($definition)) {
            /** @var array<string, mixed> $definition */
            return Policy::fromArray($definition);
        }

        if (!is_string($definition)) {
            throw InvalidDefinitionTypeException::forPolicy($name);
        }

        try {
            $parsed = json_decode($definition, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidJsonDefinitionException::forPolicy($name, $jsonException->getMessage(), $jsonException);
        }

        if (!is_array($parsed)) {
            throw InvalidParsedDefinitionException::forPolicy($name);
        }

        /** @var array<string, mixed> $parsed */
        return Policy::fromArray($parsed);
    }
}
