<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Database;

use Cline\Arbiter\Database\Models\Policy as PolicyModel;
use Illuminate\Contracts\Container\Container;

use function config;
use function is_string;

/**
 * Central registry for customizing Arbiter database models and tables.
 *
 * Provides a fluent API for overriding default model classes and table names,
 * allowing seamless integration with existing database schemas and custom
 * model implementations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ModelRegistry
{
    /**
     * Custom model class mappings.
     *
     * @var array<string, class-string>
     */
    private array $models = [];

    /**
     * Custom table name mappings.
     *
     * @var array<string, string>
     */
    private array $tables = [];

    /**
     * Create a new model registry instance.
     */
    public function __construct(
        private readonly Container $container,
    ) {
        $this->loadConfiguration();
    }

    /**
     * Set a custom model class for policies.
     *
     * @param class-string $model The custom model class
     */
    public function setPolicyModel(string $model): void
    {
        $this->models['policy'] = $model;
    }

    /**
     * Get the policy model class.
     *
     * @return class-string The policy model class
     */
    public function policyModel(): string
    {
        return $this->models['policy'] ?? PolicyModel::class;
    }

    /**
     * Set custom table names.
     *
     * @param array<string, string> $map Table name mappings
     */
    public function setTables(array $map): void
    {
        $this->tables = [...$this->tables, ...$map];
    }

    /**
     * Get a table name, falling back to the default.
     *
     * @param string $table The table identifier (e.g., 'policies')
     *
     * @return string The actual table name to use
     */
    public function table(string $table): string
    {
        return $this->tables[$table] ?? $table;
    }

    /**
     * Get the primary key type from configuration.
     *
     * @return string The primary key type ('id', 'uuid', or 'ulid')
     */
    public function primaryKeyType(): string
    {
        /** @var string */
        return config('arbiter.primary_key_type', 'id');
    }

    /**
     * Check if database storage is enabled.
     *
     * @return bool True if database storage is enabled
     */
    public function isDatabaseEnabled(): bool
    {
        /** @var bool */
        return config('arbiter.use_database', false);
    }

    /**
     * Create a new instance of the policy model.
     *
     * @param  array<string, mixed> $attributes Model attributes
     * @return PolicyModel          New model instance
     */
    public function newPolicyModel(array $attributes = []): PolicyModel
    {
        /** @var class-string<PolicyModel> */
        $class = $this->policyModel();

        return new $class($attributes);
    }

    /**
     * Resolve a model instance from the container.
     *
     * @template T
     *
     * @param  class-string<T> $class The model class to resolve
     * @return T               Resolved instance
     */
    public function resolve(string $class): mixed
    {
        return $this->container->make($class);
    }

    /**
     * Load configuration from arbiter config file.
     */
    private function loadConfiguration(): void
    {
        // Load custom models
        /** @var array<string, mixed> */
        $models = config('arbiter.models', []);

        if (isset($models['policy']) && is_string($models['policy'])) {
            /** @var class-string $policyModel */
            $policyModel = $models['policy'];
            $this->models['policy'] = $policyModel;
        }

        // Load custom tables
        /** @var array<string, string> */
        $tables = config('arbiter.tables', []);

        if (empty($tables)) {
            return;
        }

        $this->tables = $tables;
    }
}
