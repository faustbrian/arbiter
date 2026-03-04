<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\Capability;
use Cline\Arbiter\Database\Models\Policy as PolicyModel;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Rule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('PolicyModel factory methods', function (): void {
    test('fromPolicy() creates model from Policy value object', function (): void {
        // Arrange
        $policyValue = Policy::create('user-policy')
            ->description('User management policy')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read));

        // Act
        $model = PolicyModel::fromPolicy($policyValue);

        // Assert
        expect($model->name)->toBe('user-policy')
            ->and($model->description)->toBe('User management policy')
            ->and($model->rules)->toHaveCount(1)
            ->and($model->is_active)->toBeTrue();
    });

    test('fromPolicy() creates model with minimal policy data', function (): void {
        // Arrange
        $policyValue = Policy::create('simple-policy');

        // Act
        $model = PolicyModel::fromPolicy($policyValue);

        // Assert
        expect($model->name)->toBe('simple-policy')
            ->and($model->description)->toBeNull()
            ->and($model->rules)->toBe([])
            ->and($model->is_active)->toBeTrue();
    });

    test('fromPolicy() handles policy with multiple rules', function (): void {
        // Arrange
        $policyValue = Policy::create('multi-rule-policy')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read))
            ->addRule(Rule::deny('/api/admin')->capabilities(Capability::Delete))
            ->addRule(Rule::allow('/api/posts')->capabilities(Capability::Create, Capability::Update));

        // Act
        $model = PolicyModel::fromPolicy($policyValue);

        // Assert
        expect($model->name)->toBe('multi-rule-policy')
            ->and($model->rules)->toHaveCount(3)
            ->and($model->is_active)->toBeTrue();
    });

    test('findByName() returns policy when it exists', function (): void {
        // Arrange
        PolicyModel::query()->create([
            'name' => 'test-policy',
            'description' => 'Test policy',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $found = PolicyModel::findByName('test-policy');

        // Assert
        expect($found)->not->toBeNull()
            ->and($found->name)->toBe('test-policy')
            ->and($found->description)->toBe('Test policy');
    });

    test('findByName() returns null when policy does not exist', function (): void {
        // Arrange
        // No policy created

        // Act
        $found = PolicyModel::findByName('nonexistent-policy');

        // Assert
        expect($found)->toBeNull();
    });

    test('findByName() finds policy by exact name match', function (): void {
        // Arrange
        PolicyModel::query()->create([
            'name' => 'exact-match-policy',
            'description' => 'Target',
            'rules' => [],
            'is_active' => true,
        ]);
        PolicyModel::query()->create([
            'name' => 'other-policy',
            'description' => 'Other',
            'rules' => [],
            'is_active' => false,
        ]);

        // Act
        $found = PolicyModel::findByName('exact-match-policy');

        // Assert
        expect($found)->not->toBeNull()
            ->and($found->description)->toBe('Target');
    });
});

describe('PolicyModel table configuration', function (): void {
    test('getTable() returns default table name', function (): void {
        // Arrange
        config()->set('arbiter.tables.policies');
        $model = new PolicyModel();

        // Act
        $tableName = $model->getTable();

        // Assert
        expect($tableName)->toBe('policies');
    });

    test('getTable() returns configured table name', function (): void {
        // Arrange
        config()->set('arbiter.tables.policies', 'custom_policies');
        $model = new PolicyModel();

        // Act
        $tableName = $model->getTable();

        // Assert
        expect($tableName)->toBe('custom_policies');
    });

    test('getTable() falls back to default when config is null', function (): void {
        // Arrange
        config()->set('arbiter.tables.policies');
        $model = new PolicyModel();

        // Act
        $tableName = $model->getTable();

        // Assert
        expect($tableName)->toBe('policies');
    });
});

describe('PolicyModel conversion methods', function (): void {
    test('toPolicy() converts model to Policy value object', function (): void {
        // Arrange
        $model = PolicyModel::query()->create([
            'name' => 'test-policy',
            'description' => 'Test description',
            'rules' => [
                [
                    'path' => '/api/users',
                    'effect' => 'allow',
                    'capabilities' => ['read'],
                ],
            ],
            'is_active' => true,
        ]);

        // Act
        $policyValue = $model->toPolicy();

        // Assert
        expect($policyValue)->toBeInstanceOf(Policy::class)
            ->and($policyValue->getName())->toBe('test-policy')
            ->and($policyValue->getDescription())->toBe('Test description')
            ->and($policyValue->getRules())->toHaveCount(1);
    });

    test('toPolicy() converts model with null description to empty string', function (): void {
        // Arrange
        $model = PolicyModel::query()->create([
            'name' => 'test-policy',
            'description' => null,
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $policyValue = $model->toPolicy();

        // Assert
        expect($policyValue->getDescription())->toBe('');
    });

    test('toPolicy() handles model with null rules', function (): void {
        // Arrange
        $model = PolicyModel::query()->create([
            'name' => 'test-policy',
            'description' => 'Test',
            'rules' => null,
            'is_active' => true,
        ]);

        // Act
        $policyValue = $model->toPolicy();

        // Assert
        expect($policyValue->getRules())->toBe([]);
    });

    test('round-trip conversion preserves data', function (): void {
        // Arrange
        $original = Policy::create('round-trip-policy')
            ->description('Round trip test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read, Capability::Update))
            ->addRule(Rule::deny('/api/admin'));

        // Act
        $model = PolicyModel::fromPolicy($original);
        $model->save();
        $model->refresh();

        $restored = $model->toPolicy();

        // Assert
        expect($restored->getName())->toBe($original->getName())
            ->and($restored->getDescription())->toBe($original->getDescription())
            ->and($restored->getRules())->toHaveCount(2);
    });
});

describe('PolicyModel casts', function (): void {
    test('rules are cast to array', function (): void {
        // Arrange
        $model = PolicyModel::query()->create([
            'name' => 'test-policy',
            'description' => 'Test',
            'rules' => ['path' => '/test'],
            'is_active' => true,
        ]);

        // Act
        $model->refresh();

        // Assert
        expect($model->rules)->toBeArray()
            ->and($model->rules)->toBe(['path' => '/test']);
    });

    test('metadata is cast to array', function (): void {
        // Arrange
        $model = PolicyModel::query()->create([
            'name' => 'test-policy',
            'description' => 'Test',
            'rules' => [],
            'metadata' => ['author' => 'test', 'version' => '1.0'],
            'is_active' => true,
        ]);

        // Act
        $model->refresh();

        // Assert
        expect($model->metadata)->toBeArray()
            ->and($model->metadata)->toBe(['author' => 'test', 'version' => '1.0']);
    });

    test('is_active is cast to boolean', function (): void {
        // Arrange
        $model = PolicyModel::query()->create([
            'name' => 'test-policy',
            'description' => 'Test',
            'rules' => [],
            'is_active' => 1,
        ]);

        // Act
        $model->refresh();

        // Assert
        expect($model->is_active)->toBeBool()
            ->and($model->is_active)->toBeTrue();
    });

    test('created_at is cast to datetime', function (): void {
        // Arrange
        $model = PolicyModel::query()->create([
            'name' => 'test-policy',
            'description' => 'Test',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $model->refresh();

        // Assert
        expect($model->created_at)->toBeInstanceOf(Carbon::class);
    });

    test('updated_at is cast to datetime', function (): void {
        // Arrange
        $model = PolicyModel::query()->create([
            'name' => 'test-policy',
            'description' => 'Test',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $model->refresh();

        // Assert
        expect($model->updated_at)->toBeInstanceOf(Carbon::class);
    });

    test('null metadata is cast to null array', function (): void {
        // Arrange
        $model = PolicyModel::query()->create([
            'name' => 'test-policy',
            'description' => 'Test',
            'rules' => [],
            'metadata' => null,
            'is_active' => true,
        ]);

        // Act
        $model->refresh();

        // Assert
        expect($model->metadata)->toBeNull();
    });
});

describe('PolicyModel scopes - active()', function (): void {
    test('active() returns only active policies', function (): void {
        // Arrange
        PolicyModel::query()->create([
            'name' => 'active-policy-1',
            'description' => 'Active 1',
            'rules' => [],
            'is_active' => true,
        ]);
        PolicyModel::query()->create([
            'name' => 'active-policy-2',
            'description' => 'Active 2',
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
        $activePolicies = PolicyModel::active()->get();

        // Assert
        expect($activePolicies)->toHaveCount(2)
            ->and($activePolicies->pluck('name')->toArray())->toBe(['active-policy-1', 'active-policy-2']);
    });

    test('active() returns empty collection when no active policies exist', function (): void {
        // Arrange
        PolicyModel::query()->create([
            'name' => 'inactive-policy-1',
            'description' => 'Inactive 1',
            'rules' => [],
            'is_active' => false,
        ]);
        PolicyModel::query()->create([
            'name' => 'inactive-policy-2',
            'description' => 'Inactive 2',
            'rules' => [],
            'is_active' => false,
        ]);

        // Act
        $activePolicies = PolicyModel::active()->get();

        // Assert
        expect($activePolicies)->toHaveCount(0)
            ->and($activePolicies)->toBeEmpty();
    });

    test('active() can be chained with other query methods', function (): void {
        // Arrange
        PolicyModel::query()->create([
            'name' => 'active-alpha',
            'description' => 'Active Alpha',
            'rules' => [],
            'is_active' => true,
        ]);
        PolicyModel::query()->create([
            'name' => 'active-beta',
            'description' => 'Active Beta',
            'rules' => [],
            'is_active' => true,
        ]);
        PolicyModel::query()->create([
            'name' => 'inactive-gamma',
            'description' => 'Inactive Gamma',
            'rules' => [],
            'is_active' => false,
        ]);

        // Act
        $result = PolicyModel::active()->where('name', 'active-alpha')->first();

        // Assert
        expect($result)->not->toBeNull()
            ->and($result->name)->toBe('active-alpha');
    });

    test('active() scope filters correctly with orderBy', function (): void {
        // Arrange
        PolicyModel::query()->create([
            'name' => 'zeta-active',
            'description' => 'Zeta',
            'rules' => [],
            'is_active' => true,
        ]);
        PolicyModel::query()->create([
            'name' => 'alpha-active',
            'description' => 'Alpha',
            'rules' => [],
            'is_active' => true,
        ]);
        PolicyModel::query()->create([
            'name' => 'beta-inactive',
            'description' => 'Beta',
            'rules' => [],
            'is_active' => false,
        ]);

        // Act
        $results = PolicyModel::active()->orderBy('name')->get();

        // Assert
        expect($results)->toHaveCount(2)
            ->and($results->first()->name)->toBe('alpha-active')
            ->and($results->last()->name)->toBe('zeta-active');
    });

    test('active() scope can be used with count()', function (): void {
        // Arrange
        PolicyModel::query()->create(['name' => 'active-1', 'rules' => [], 'is_active' => true]);
        PolicyModel::query()->create(['name' => 'active-2', 'rules' => [], 'is_active' => true]);
        PolicyModel::query()->create(['name' => 'active-3', 'rules' => [], 'is_active' => true]);
        PolicyModel::query()->create(['name' => 'inactive-1', 'rules' => [], 'is_active' => false]);

        // Act
        $count = PolicyModel::active()->count();

        // Assert
        expect($count)->toBe(3);
    });
});

describe('PolicyModel scopes - inactive()', function (): void {
    test('inactive() returns only inactive policies', function (): void {
        // Arrange
        PolicyModel::query()->create([
            'name' => 'inactive-policy-1',
            'description' => 'Inactive 1',
            'rules' => [],
            'is_active' => false,
        ]);
        PolicyModel::query()->create([
            'name' => 'inactive-policy-2',
            'description' => 'Inactive 2',
            'rules' => [],
            'is_active' => false,
        ]);
        PolicyModel::query()->create([
            'name' => 'active-policy',
            'description' => 'Active',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $inactivePolicies = PolicyModel::inactive()->get();

        // Assert
        expect($inactivePolicies)->toHaveCount(2)
            ->and($inactivePolicies->pluck('name')->toArray())->toBe(['inactive-policy-1', 'inactive-policy-2']);
    });

    test('inactive() returns empty collection when no inactive policies exist', function (): void {
        // Arrange
        PolicyModel::query()->create([
            'name' => 'active-policy-1',
            'description' => 'Active 1',
            'rules' => [],
            'is_active' => true,
        ]);
        PolicyModel::query()->create([
            'name' => 'active-policy-2',
            'description' => 'Active 2',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $inactivePolicies = PolicyModel::inactive()->get();

        // Assert
        expect($inactivePolicies)->toHaveCount(0)
            ->and($inactivePolicies)->toBeEmpty();
    });

    test('inactive() can be chained with other query methods', function (): void {
        // Arrange
        PolicyModel::query()->create([
            'name' => 'inactive-alpha',
            'description' => 'Inactive Alpha',
            'rules' => [],
            'is_active' => false,
        ]);
        PolicyModel::query()->create([
            'name' => 'inactive-beta',
            'description' => 'Inactive Beta',
            'rules' => [],
            'is_active' => false,
        ]);
        PolicyModel::query()->create([
            'name' => 'active-gamma',
            'description' => 'Active Gamma',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $result = PolicyModel::inactive()->where('name', 'inactive-beta')->first();

        // Assert
        expect($result)->not->toBeNull()
            ->and($result->name)->toBe('inactive-beta');
    });

    test('inactive() scope filters correctly with orderBy', function (): void {
        // Arrange
        PolicyModel::query()->create([
            'name' => 'zeta-inactive',
            'description' => 'Zeta',
            'rules' => [],
            'is_active' => false,
        ]);
        PolicyModel::query()->create([
            'name' => 'alpha-inactive',
            'description' => 'Alpha',
            'rules' => [],
            'is_active' => false,
        ]);
        PolicyModel::query()->create([
            'name' => 'beta-active',
            'description' => 'Beta',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $results = PolicyModel::inactive()->orderBy('name')->get();

        // Assert
        expect($results)->toHaveCount(2)
            ->and($results->first()->name)->toBe('alpha-inactive')
            ->and($results->last()->name)->toBe('zeta-inactive');
    });

    test('inactive() scope can be used with count()', function (): void {
        // Arrange
        PolicyModel::query()->create(['name' => 'inactive-1', 'rules' => [], 'is_active' => false]);
        PolicyModel::query()->create(['name' => 'inactive-2', 'rules' => [], 'is_active' => false]);
        PolicyModel::query()->create(['name' => 'inactive-3', 'rules' => [], 'is_active' => false]);
        PolicyModel::query()->create(['name' => 'active-1', 'rules' => [], 'is_active' => true]);

        // Act
        $count = PolicyModel::inactive()->count();

        // Assert
        expect($count)->toBe(3);
    });

    test('inactive() scope excludes policies with true is_active', function (): void {
        // Arrange
        PolicyModel::query()->create(['name' => 'inactive-1', 'rules' => [], 'is_active' => false]);
        PolicyModel::query()->create(['name' => 'active-1', 'rules' => [], 'is_active' => true]);
        PolicyModel::query()->create(['name' => 'inactive-2', 'rules' => [], 'is_active' => false]);

        // Act
        $results = PolicyModel::inactive()->get();

        // Assert
        expect($results)->toHaveCount(2)
            ->and($results->contains('name', 'active-1'))->toBeFalse();
    });
});

describe('PolicyModel scope combinations', function (): void {
    test('cannot combine active() and inactive() scopes meaningfully', function (): void {
        // Arrange
        PolicyModel::query()->create(['name' => 'active-1', 'rules' => [], 'is_active' => true]);
        PolicyModel::query()->create(['name' => 'inactive-1', 'rules' => [], 'is_active' => false]);

        // Act
        $results = PolicyModel::active()->inactive()->get();

        // Assert
        // Should return empty because a policy cannot be both active and inactive
        expect($results)->toHaveCount(0);
    });

    test('active() scope can be used with where clauses', function (): void {
        // Arrange
        PolicyModel::query()->create([
            'name' => 'test-active',
            'description' => 'Test Description',
            'rules' => [],
            'is_active' => true,
        ]);
        PolicyModel::query()->create([
            'name' => 'other-active',
            'description' => 'Other Description',
            'rules' => [],
            'is_active' => true,
        ]);
        PolicyModel::query()->create([
            'name' => 'test-inactive',
            'description' => 'Test Description',
            'rules' => [],
            'is_active' => false,
        ]);

        // Act
        $results = PolicyModel::active()->where('description', 'Test Description')->get();

        // Assert
        expect($results)->toHaveCount(1)
            ->and($results->first()->name)->toBe('test-active');
    });

    test('inactive() scope can be used with where clauses', function (): void {
        // Arrange
        PolicyModel::query()->create([
            'name' => 'test-inactive',
            'description' => 'Test Description',
            'rules' => [],
            'is_active' => false,
        ]);
        PolicyModel::query()->create([
            'name' => 'other-inactive',
            'description' => 'Other Description',
            'rules' => [],
            'is_active' => false,
        ]);
        PolicyModel::query()->create([
            'name' => 'test-active',
            'description' => 'Test Description',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $results = PolicyModel::inactive()->where('description', 'Test Description')->get();

        // Assert
        expect($results)->toHaveCount(1)
            ->and($results->first()->name)->toBe('test-inactive');
    });
});

describe('PolicyModel edge cases', function (): void {
    test('handles empty rules array', function (): void {
        // Arrange
        $model = PolicyModel::query()->create([
            'name' => 'empty-rules',
            'description' => 'Test',
            'rules' => [],
            'is_active' => true,
        ]);

        // Act
        $model->refresh();

        // Assert
        expect($model->rules)->toBe([])
            ->and($model->rules)->toBeArray();
    });

    test('handles very long policy name', function (): void {
        // Arrange
        $longName = str_repeat('a', 255);

        // Act
        $model = PolicyModel::query()->create([
            'name' => $longName,
            'description' => 'Test',
            'rules' => [],
            'is_active' => true,
        ]);

        // Assert
        expect($model->name)->toBe($longName);
    });

    test('handles very long description', function (): void {
        // Arrange
        $longDescription = str_repeat('This is a very long description. ', 100);

        // Act
        $model = PolicyModel::query()->create([
            'name' => 'test-policy',
            'description' => $longDescription,
            'rules' => [],
            'is_active' => true,
        ]);

        // Assert
        expect($model->description)->toBe($longDescription);
    });

    test('handles complex nested rules structure', function (): void {
        // Arrange
        $complexRules = [
            [
                'path' => '/api/users/${id}',
                'effect' => 'allow',
                'capabilities' => ['read', 'update', 'delete'],
                'conditions' => [
                    'role' => ['admin', 'moderator'],
                    'department' => 'engineering',
                ],
                'description' => 'Complex rule with multiple conditions',
            ],
            [
                'path' => '/api/admin/**',
                'effect' => 'deny',
                'capabilities' => ['*'],
            ],
        ];

        // Act
        $model = PolicyModel::query()->create([
            'name' => 'complex-policy',
            'description' => 'Complex test',
            'rules' => $complexRules,
            'is_active' => true,
        ]);
        $model->refresh();

        // Assert
        expect($model->rules)->toBe($complexRules)
            ->and($model->rules[0]['conditions'])->toBe(['role' => ['admin', 'moderator'], 'department' => 'engineering']);
    });

    test('handles metadata with nested structures', function (): void {
        // Arrange
        $metadata = [
            'author' => 'test-user',
            'version' => '1.0.0',
            'tags' => ['security', 'access-control', 'api'],
            'settings' => [
                'cache_ttl' => 3_600,
                'strict_mode' => true,
            ],
        ];

        // Act
        $model = PolicyModel::query()->create([
            'name' => 'metadata-test',
            'description' => 'Test',
            'rules' => [],
            'metadata' => $metadata,
            'is_active' => true,
        ]);
        $model->refresh();

        // Assert
        expect($model->metadata)->toBe($metadata)
            ->and($model->metadata['settings']['cache_ttl'])->toBe(3_600);
    });

    test('handles unicode characters in name', function (): void {
        // Arrange
        $unicodeName = 'policy-测试-مختبر-тест';

        // Act
        $model = PolicyModel::query()->create([
            'name' => $unicodeName,
            'description' => 'Unicode test',
            'rules' => [],
            'is_active' => true,
        ]);

        // Assert
        expect($model->name)->toBe($unicodeName);
    });

    test('handles unicode characters in description', function (): void {
        // Arrange
        $unicodeDescription = 'This is a test with unicode: 测试 مختبر тест 🚀';

        // Act
        $model = PolicyModel::query()->create([
            'name' => 'unicode-test',
            'description' => $unicodeDescription,
            'rules' => [],
            'is_active' => true,
        ]);

        // Assert
        expect($model->description)->toBe($unicodeDescription);
    });
});

describe('PolicyModel mass assignment', function (): void {
    test('fillable attributes can be mass assigned', function (): void {
        // Arrange
        $data = [
            'name' => 'mass-assigned',
            'description' => 'Mass assignment test',
            'rules' => [['path' => '/test']],
            'metadata' => ['key' => 'value'],
            'is_active' => false,
        ];

        // Act
        $model = new PolicyModel($data);

        // Assert
        expect($model->name)->toBe('mass-assigned')
            ->and($model->description)->toBe('Mass assignment test')
            ->and($model->rules)->toBe([['path' => '/test']])
            ->and($model->metadata)->toBe(['key' => 'value'])
            ->and($model->is_active)->toBeFalse();
    });

    test('all fillable attributes are included in fillable array', function (): void {
        // Arrange
        $model = new PolicyModel();

        // Act
        $fillable = $model->getFillable();

        // Assert
        expect($fillable)->toContain('name')
            ->and($fillable)->toContain('description')
            ->and($fillable)->toContain('rules')
            ->and($fillable)->toContain('metadata')
            ->and($fillable)->toContain('is_active');
    });
});
