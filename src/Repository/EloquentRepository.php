<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Repository;

use Cline\Arbiter\Database\ModelRegistry;
use Cline\Arbiter\Database\Models\Policy as PolicyModel;
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;
use Illuminate\Database\Eloquent\Collection;

/**
 * Eloquent-based policy repository using Laravel's ORM.
 *
 * Provides policy storage and retrieval using Laravel's Eloquent ORM,
 * with support for custom models and table names via ModelRegistry.
 * Automatically converts between Policy value objects and Eloquent models.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class EloquentRepository implements PolicyRepositoryInterface
{
    /**
     * @param ModelRegistry $registry Model registry for custom models/tables
     */
    public function __construct(
        private ModelRegistry $registry,
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
        /** @var class-string<PolicyModel> $modelClass */
        $modelClass = $this->registry->policyModel();

        /** @var null|PolicyModel $model */
        $model = $modelClass::query()
            ->where('name', $name)
            ->where('is_active', true)
            ->first();

        if ($model === null) {
            throw PolicyNotFoundException::forName($name);
        }

        return $model->toPolicy();
    }

    /**
     * Check if a policy exists.
     *
     * @param  string $name The policy name
     * @return bool   True if the policy exists and is active, false otherwise
     */
    public function has(string $name): bool
    {
        /** @var class-string<PolicyModel> $modelClass */
        $modelClass = $this->registry->policyModel();

        return $modelClass::query()
            ->where('name', $name)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get all active policies.
     *
     * @return array<string, Policy> Map of policy names to instances
     */
    public function all(): array
    {
        /** @var class-string<PolicyModel> $modelClass */
        $modelClass = $this->registry->policyModel();

        /** @var Collection<int, PolicyModel> $models */
        $models = $modelClass::query()
            ->where('is_active', true)
            ->get();

        return $models->mapWithKeys(fn (PolicyModel $model): array => [$model->name => $model->toPolicy()])->all();
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

        /** @var class-string<PolicyModel> $modelClass */
        $modelClass = $this->registry->policyModel();

        /** @var Collection<int, PolicyModel> $models */
        $models = $modelClass::query()
            ->whereIn('name', $names)
            ->where('is_active', true)
            ->get();

        return $models->mapWithKeys(fn (PolicyModel $model): array => [$model->name => $model->toPolicy()])->all();
    }

    /**
     * Create or update a policy.
     *
     * @param Policy $policy The policy to store
     *
     * @return PolicyModel The stored model
     */
    public function save(Policy $policy): PolicyModel
    {
        /** @var class-string<PolicyModel> $modelClass */
        $modelClass = $this->registry->policyModel();

        $data = $policy->jsonSerialize();

        return $modelClass::query()->updateOrCreate(
            ['name' => $data['name']],
            [
                'description' => $data['description'],
                'rules' => $data['rules'],
                'is_active' => true,
            ],
        );
    }

    /**
     * Delete a policy by name.
     *
     * @param string $name The policy name to delete
     *
     * @return bool True if deleted, false if not found
     */
    public function delete(string $name): bool
    {
        /** @var class-string<PolicyModel> $modelClass */
        $modelClass = $this->registry->policyModel();

        $deleted = $modelClass::query()
            ->where('name', $name)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Deactivate a policy instead of deleting it.
     *
     * @param string $name The policy name to deactivate
     *
     * @return bool True if deactivated, false if not found
     */
    public function deactivate(string $name): bool
    {
        /** @var class-string<PolicyModel> $modelClass */
        $modelClass = $this->registry->policyModel();

        $updated = $modelClass::query()
            ->where('name', $name)
            ->update(['is_active' => false]);

        return $updated > 0;
    }

    /**
     * Reactivate a deactivated policy.
     *
     * @param string $name The policy name to reactivate
     *
     * @return bool True if reactivated, false if not found
     */
    public function reactivate(string $name): bool
    {
        /** @var class-string<PolicyModel> $modelClass */
        $modelClass = $this->registry->policyModel();

        $updated = $modelClass::query()
            ->where('name', $name)
            ->update(['is_active' => true]);

        return $updated > 0;
    }
}
