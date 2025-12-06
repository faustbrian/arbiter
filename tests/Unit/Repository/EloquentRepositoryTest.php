<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\Database\ModelRegistry;
use Cline\Arbiter\Database\Models\Policy as PolicyModel;
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Repository\EloquentRepository;
use Cline\Arbiter\Rule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EloquentRepository get() method', function (): void {
    test('get() retrieves active policy by name successfully', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        PolicyModel::query()->create([
            'name' => 'admin-policy',
            'description' => 'Admin access policy',
            'rules' => [
                ['path' => '/admin/*', 'effect' => 'allow', 'capabilities' => ['read', 'update']],
            ],
            'is_active' => true,
        ]);

        // Act
        $result = $repository->get('admin-policy');

        // Assert
        expect($result)->toBeInstanceOf(Policy::class)
            ->and($result->getName())->toBe('admin-policy')
            ->and($result->getDescription())->toBe('Admin access policy')
            ->and($result->getRules())->toHaveCount(1);
    });

    test('get() returns policy with all fields populated', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        PolicyModel::query()->create([
            'name' => 'user-policy',
            'description' => 'User policy description',
            'rules' => [
                ['path' => '/users/*', 'effect' => 'allow', 'capabilities' => ['read']],
                ['path' => '/admin/*', 'effect' => 'deny', 'capabilities' => ['update']],
            ],
            'is_active' => true,
        ]);

        // Act
        $result = $repository->get('user-policy');

        // Assert
        expect($result->getName())->toBe('user-policy')
            ->and($result->getDescription())->toBe('User policy description')
            ->and($result->getRules())->toHaveCount(2);
    });

    test('get() throws PolicyNotFoundException for non-existent policy', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        // Act & Assert
        expect(fn (): Policy => $repository->get('nonexistent'))
            ->toThrow(PolicyNotFoundException::class, 'Policy not found: nonexistent');
    });

    test('get() throws PolicyNotFoundException for inactive policy', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        PolicyModel::query()->create([
            'name' => 'inactive-policy',
            'description' => 'Inactive policy',
            'rules' => [],
            'is_active' => false,
        ]);

        // Act & Assert
        expect(fn (): Policy => $repository->get('inactive-policy'))
            ->toThrow(PolicyNotFoundException::class, 'Policy not found: inactive-policy');
    });
});

describe('EloquentRepository has() method', function (): void {
    test('has() returns true for existing active policy', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        PolicyModel::query()->create([
            'name' => 'test-policy',
            'description' => 'Test',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $result = $repository->has('test-policy');

        // Assert
        expect($result)->toBeTrue();
    });

    test('has() returns false for non-existent policy', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        // Act
        $result = $repository->has('nonexistent');

        // Assert
        expect($result)->toBeFalse();
    });

    test('has() returns false for inactive policy', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        PolicyModel::query()->create([
            'name' => 'inactive-policy',
            'description' => 'Inactive',
            'rules' => [],
            'is_active' => false,
        ]);

        // Act
        $result = $repository->has('inactive-policy');

        // Assert
        expect($result)->toBeFalse();
    });
});

describe('EloquentRepository all() method', function (): void {
    test('all() returns all active policies as array map', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        PolicyModel::query()->create([
            'name' => 'policy-1',
            'description' => 'First policy',
            'rules' => [],
            'is_active' => true,
        ]);

        PolicyModel::query()->create([
            'name' => 'policy-2',
            'description' => 'Second policy',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $result = $repository->all();

        // Assert
        expect($result)->toBeArray()
            ->and($result)->toHaveCount(2)
            ->and($result)->toHaveKeys(['policy-1', 'policy-2'])
            ->and($result['policy-1'])->toBeInstanceOf(Policy::class)
            ->and($result['policy-2'])->toBeInstanceOf(Policy::class);
    });

    test('all() returns empty array when no policies exist', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        // Act
        $result = $repository->all();

        // Assert
        expect($result)->toBeArray()
            ->and($result)->toBe([]);
    });

    test('all() excludes inactive policies from results', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        PolicyModel::query()->create([
            'name' => 'active-policy',
            'description' => 'Active',
            'rules' => [],
            'is_active' => true,
        ]);

        PolicyModel::query()->create([
            'name' => 'inactive-policy',
            'description' => 'Inactive',
            'rules' => [],
            'is_active' => false,
        ]);

        // Act
        $result = $repository->all();

        // Assert
        expect($result)->toHaveCount(1)
            ->and($result)->toHaveKey('active-policy')
            ->and($result)->not->toHaveKey('inactive-policy');
    });

    test('all() returns policies keyed by name', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        PolicyModel::query()->create([
            'name' => 'admin-access',
            'description' => 'Admin',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $result = $repository->all();

        // Assert
        expect($result)->toHaveKey('admin-access')
            ->and($result['admin-access']->getName())->toBe('admin-access');
    });
});

describe('EloquentRepository getMany() method', function (): void {
    test('getMany() returns policies for given names', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        PolicyModel::query()->create([
            'name' => 'policy-1',
            'description' => 'First',
            'rules' => [],
            'is_active' => true,
        ]);

        PolicyModel::query()->create([
            'name' => 'policy-2',
            'description' => 'Second',
            'rules' => [],
            'is_active' => true,
        ]);

        PolicyModel::query()->create([
            'name' => 'policy-3',
            'description' => 'Third',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $result = $repository->getMany(['policy-1', 'policy-3']);

        // Assert
        expect($result)->toHaveCount(2)
            ->and($result)->toHaveKeys(['policy-1', 'policy-3'])
            ->and($result)->not->toHaveKey('policy-2');
    });

    test('getMany() returns empty array when names array is empty', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        // Act
        $result = $repository->getMany([]);

        // Assert
        expect($result)->toBe([]);
    });

    test('getMany() excludes inactive policies from results', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        PolicyModel::query()->create([
            'name' => 'active-policy',
            'description' => 'Active',
            'rules' => [],
            'is_active' => true,
        ]);

        PolicyModel::query()->create([
            'name' => 'inactive-policy',
            'description' => 'Inactive',
            'rules' => [],
            'is_active' => false,
        ]);

        // Act
        $result = $repository->getMany(['active-policy', 'inactive-policy']);

        // Assert
        expect($result)->toHaveCount(1)
            ->and($result)->toHaveKey('active-policy')
            ->and($result)->not->toHaveKey('inactive-policy');
    });

    test('getMany() returns partial results when some names do not exist', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        PolicyModel::query()->create([
            'name' => 'existing-policy',
            'description' => 'Exists',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $result = $repository->getMany(['existing-policy', 'nonexistent-policy']);

        // Assert
        expect($result)->toHaveCount(1)
            ->and($result)->toHaveKey('existing-policy')
            ->and($result)->not->toHaveKey('nonexistent-policy');
    });
});

describe('EloquentRepository save() method', function (): void {
    test('save() creates new policy successfully', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        $policy = Policy::create('new-policy')
            ->description('New policy description')
            ->addRule(Rule::allow('/test/*')->capabilities());

        // Act
        $model = $repository->save($policy);

        // Assert
        expect($model)->toBeInstanceOf(PolicyModel::class)
            ->and($model->name)->toBe('new-policy')
            ->and($model->description)->toBe('New policy description')
            ->and($model->is_active)->toBeTrue();

        $this->assertDatabaseHas('policies', [
            'name' => 'new-policy',
            'description' => 'New policy description',
            'is_active' => true,
        ]);
    });

    test('save() updates existing policy', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        $existing = PolicyModel::query()->create([
            'name' => 'existing-policy',
            'description' => 'Old description',
            'rules' => [],
            'is_active' => true,
        ]);

        $policy = Policy::create('existing-policy')
            ->description('Updated description')
            ->addRule(Rule::allow('/new/*')->capabilities());

        // Act
        $model = $repository->save($policy);

        // Assert
        expect($model->id)->toBe($existing->id)
            ->and($model->name)->toBe('existing-policy')
            ->and($model->description)->toBe('Updated description');

        $this->assertDatabaseHas('policies', [
            'name' => 'existing-policy',
            'description' => 'Updated description',
        ]);

        $this->assertDatabaseCount('policies', 1);
    });

    test('save() handles policy with complex rules array', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        $policy = Policy::create('complex-policy')
            ->description('Complex policy')
            ->addRule(Rule::allow('/admin/*')->capabilities())
            ->addRule(Rule::deny('/admin/secret/*')->capabilities());

        // Act
        $model = $repository->save($policy);

        // Assert
        expect($model->rules)->toBeArray()
            ->and($model->rules)->toHaveCount(2);
    });
});

describe('EloquentRepository delete() method', function (): void {
    test('delete() deletes policy and returns true', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        PolicyModel::query()->create([
            'name' => 'delete-me',
            'description' => 'To be deleted',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $result = $repository->delete('delete-me');

        // Assert
        expect($result)->toBeTrue();

        $this->assertDatabaseMissing('policies', [
            'name' => 'delete-me',
        ]);
    });

    test('delete() returns false when policy does not exist', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        // Act
        $result = $repository->delete('nonexistent');

        // Assert
        expect($result)->toBeFalse();
    });
});

describe('EloquentRepository deactivate() method', function (): void {
    test('deactivate() sets is_active to false and returns true', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        PolicyModel::query()->create([
            'name' => 'active-policy',
            'description' => 'Active',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $result = $repository->deactivate('active-policy');

        // Assert
        expect($result)->toBeTrue();

        $this->assertDatabaseHas('policies', [
            'name' => 'active-policy',
            'is_active' => false,
        ]);
    });

    test('deactivate() returns false when policy does not exist', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        // Act
        $result = $repository->deactivate('nonexistent');

        // Assert
        expect($result)->toBeFalse();
    });
});

describe('EloquentRepository reactivate() method', function (): void {
    test('reactivate() sets is_active to true and returns true', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        PolicyModel::query()->create([
            'name' => 'inactive-policy',
            'description' => 'Inactive',
            'rules' => [],
            'is_active' => false,
        ]);

        // Act
        $result = $repository->reactivate('inactive-policy');

        // Assert
        expect($result)->toBeTrue();

        $this->assertDatabaseHas('policies', [
            'name' => 'inactive-policy',
            'is_active' => true,
        ]);
    });

    test('reactivate() returns false when policy does not exist', function (): void {
        // Arrange
        $registry = resolve(ModelRegistry::class);
        $repository = new EloquentRepository($registry);

        // Act
        $result = $repository->reactivate('nonexistent');

        // Assert
        expect($result)->toBeFalse();
    });
});
