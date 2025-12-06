<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\Exception\InvalidDefinitionTypeException;
use Cline\Arbiter\Exception\InvalidJsonDefinitionException;
use Cline\Arbiter\Exception\InvalidParsedDefinitionException;
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Repository\DatabaseRepository;

describe('DatabaseRepository constructor', function (): void {
    test('constructor creates repository with default table name', function (): void {
        $pdo = Mockery::mock(PDO::class);
        $repository = new DatabaseRepository($pdo);

        expect($repository)->toBeInstanceOf(DatabaseRepository::class);
    });

    test('constructor accepts custom table name', function (): void {
        $pdo = Mockery::mock(PDO::class);
        $repository = new DatabaseRepository($pdo, 'custom_policies');

        expect($repository)->toBeInstanceOf(DatabaseRepository::class);
    });

    test('constructor accepts custom column names', function (): void {
        $pdo = Mockery::mock(PDO::class);
        $repository = new DatabaseRepository(
            $pdo,
            'policies',
            'policy_name',
            'policy_data',
        );

        expect($repository)->toBeInstanceOf(DatabaseRepository::class);
    });

    test('constructor accepts additional WHERE conditions', function (): void {
        $pdo = Mockery::mock(PDO::class);
        $repository = new DatabaseRepository(
            $pdo,
            'policies',
            'name',
            'definition',
            ['tenant_id' => '123', 'active' => true],
        );

        expect($repository)->toBeInstanceOf(DatabaseRepository::class);
    });
});

describe('DatabaseRepository get() method', function (): void {
    test('get() returns policy by name with valid JSON definition', function (): void {
        // Arrange
        $policyData = [
            'name' => 'user-policy',
            'description' => 'User access policy',
            'rules' => [
                ['path' => '/api/users', 'effect' => 'allow'],
            ],
        ];
        $jsonDefinition = json_encode($policyData);

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['user-policy']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'user-policy', 'definition' => $jsonDefinition]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->with('SELECT * FROM policies WHERE name = ?')
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $policy = $repository->get('user-policy');

        // Assert
        expect($policy)->toBeInstanceOf(Policy::class)
            ->and($policy->getName())->toBe('user-policy')
            ->and($policy->getDescription())->toBe('User access policy')
            ->and($policy->getRules())->toHaveCount(1);
    });

    test('get() returns policy with array definition', function (): void {
        // Arrange
        $policyData = [
            'name' => 'admin-policy',
            'description' => 'Admin access',
            'rules' => [],
        ];

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['admin-policy']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'admin-policy', 'definition' => $policyData]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $policy = $repository->get('admin-policy');

        // Assert
        expect($policy)->toBeInstanceOf(Policy::class)
            ->and($policy->getName())->toBe('admin-policy')
            ->and($policy->getDescription())->toBe('Admin access');
    });

    test('get() throws PolicyNotFoundException when policy does not exist', function (): void {
        // Arrange
        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['nonexistent']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(false);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act & Assert
        expect(fn (): Policy => $repository->get('nonexistent'))
            ->toThrow(PolicyNotFoundException::class, 'Policy not found: nonexistent');
    });

    test('get() applies additional conditions from constructor', function (): void {
        // Arrange
        $policyData = ['name' => 'test-policy', 'rules' => []];
        $jsonDefinition = json_encode($policyData);

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['tenant-123', 'test-policy']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'test-policy', 'definition' => $jsonDefinition]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->with('SELECT * FROM policies WHERE tenant_id = ? AND name = ?')
            ->andReturn($stmt);

        $repository = new DatabaseRepository(
            $pdo,
            'policies',
            'name',
            'definition',
            ['tenant_id' => 'tenant-123'],
        );

        // Act
        $policy = $repository->get('test-policy');

        // Assert
        expect($policy)->toBeInstanceOf(Policy::class);
    });

    test('get() uses custom column names from constructor', function (): void {
        // Arrange
        $policyData = ['name' => 'custom-policy', 'rules' => []];
        $jsonDefinition = json_encode($policyData);

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['custom-policy']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['policy_name' => 'custom-policy', 'policy_data' => $jsonDefinition]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->with('SELECT * FROM custom_table WHERE policy_name = ?')
            ->andReturn($stmt);

        $repository = new DatabaseRepository(
            $pdo,
            'custom_table',
            'policy_name',
            'policy_data',
        );

        // Act
        $policy = $repository->get('custom-policy');

        // Assert
        expect($policy)->toBeInstanceOf(Policy::class);
    });
});

describe('DatabaseRepository has() method', function (): void {
    test('has() returns true when policy exists', function (): void {
        // Arrange
        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['existing-policy']);
        $stmt->shouldReceive('fetchColumn')
            ->once()
            ->andReturn(1);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->with('SELECT COUNT(*) FROM policies WHERE name = ?')
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act & Assert
        expect($repository->has('existing-policy'))->toBeTrue();
    });

    test('has() returns false when policy does not exist', function (): void {
        // Arrange
        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['nonexistent']);
        $stmt->shouldReceive('fetchColumn')
            ->once()
            ->andReturn(0);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->with('SELECT COUNT(*) FROM policies WHERE name = ?')
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act & Assert
        expect($repository->has('nonexistent'))->toBeFalse();
    });

    test('has() applies additional conditions from constructor', function (): void {
        // Arrange
        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['active-tenant', 'test-policy']);
        $stmt->shouldReceive('fetchColumn')
            ->once()
            ->andReturn(1);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->with('SELECT COUNT(*) FROM policies WHERE tenant_id = ? AND name = ?')
            ->andReturn($stmt);

        $repository = new DatabaseRepository(
            $pdo,
            'policies',
            'name',
            'definition',
            ['tenant_id' => 'active-tenant'],
        );

        // Act & Assert
        expect($repository->has('test-policy'))->toBeTrue();
    });

    test('has() uses custom column names', function (): void {
        // Arrange
        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['policy-name']);
        $stmt->shouldReceive('fetchColumn')
            ->once()
            ->andReturn(1);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->with('SELECT COUNT(*) FROM custom_policies WHERE policy_name = ?')
            ->andReturn($stmt);

        $repository = new DatabaseRepository(
            $pdo,
            'custom_policies',
            'policy_name',
            'policy_data',
        );

        // Act & Assert
        expect($repository->has('policy-name'))->toBeTrue();
    });
});

describe('DatabaseRepository all() method', function (): void {
    test('all() returns all policies from database', function (): void {
        // Arrange
        $policy1Data = ['name' => 'policy-1', 'description' => 'First', 'rules' => []];
        $policy2Data = ['name' => 'policy-2', 'description' => 'Second', 'rules' => []];

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with([]);
        $stmt->shouldReceive('fetch')
            ->times(3)
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(
                ['name' => 'policy-1', 'definition' => json_encode($policy1Data)],
                ['name' => 'policy-2', 'definition' => json_encode($policy2Data)],
                false,
            );

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->with('SELECT * FROM policies')
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $policies = $repository->all();

        // Assert
        expect($policies)->toHaveCount(2)
            ->and($policies)->toHaveKey('policy-1')
            ->and($policies)->toHaveKey('policy-2')
            ->and($policies['policy-1']->getName())->toBe('policy-1')
            ->and($policies['policy-2']->getName())->toBe('policy-2');
    });

    test('all() returns empty array when no policies exist', function (): void {
        // Arrange
        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with([]);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(false);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->with('SELECT * FROM policies')
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $policies = $repository->all();

        // Assert
        expect($policies)->toBe([]);
    });

    test('all() caches results on subsequent calls', function (): void {
        // Arrange
        $policyData = ['name' => 'cached-policy', 'rules' => []];

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once() // Should only be called once
            ->with([]);
        $stmt->shouldReceive('fetch')
            ->times(2)
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(
                ['name' => 'cached-policy', 'definition' => json_encode($policyData)],
                false,
            );

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once() // Should only be called once
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $firstCall = $repository->all();
        $secondCall = $repository->all();

        // Assert
        expect($firstCall)->toBe($secondCall)
            ->and($firstCall)->toHaveCount(1);
    });

    test('all() applies additional conditions from constructor', function (): void {
        // Arrange
        $policyData = ['name' => 'tenant-policy', 'rules' => []];

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['tenant-456', true]);
        $stmt->shouldReceive('fetch')
            ->times(2)
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(
                ['name' => 'tenant-policy', 'definition' => json_encode($policyData)],
                false,
            );

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->with('SELECT * FROM policies WHERE tenant_id = ? AND active = ?')
            ->andReturn($stmt);

        $repository = new DatabaseRepository(
            $pdo,
            'policies',
            'name',
            'definition',
            ['tenant_id' => 'tenant-456', 'active' => true],
        );

        // Act
        $policies = $repository->all();

        // Assert
        expect($policies)->toHaveCount(1);
    });
});

describe('DatabaseRepository getMany() method', function (): void {
    test('getMany() returns empty array when given empty array', function (): void {
        // Arrange
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldNotReceive('prepare');

        $repository = new DatabaseRepository($pdo);

        // Act
        $policies = $repository->getMany([]);

        // Assert
        expect($policies)->toBe([]);
    });

    test('getMany() returns multiple policies by name', function (): void {
        // Arrange
        $policy1Data = ['name' => 'policy-1', 'rules' => []];
        $policy2Data = ['name' => 'policy-2', 'rules' => []];

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['policy-1', 'policy-2']);
        $stmt->shouldReceive('fetch')
            ->times(3)
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(
                ['name' => 'policy-1', 'definition' => json_encode($policy1Data)],
                ['name' => 'policy-2', 'definition' => json_encode($policy2Data)],
                false,
            );

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->with('SELECT * FROM policies WHERE 1=1 AND name IN (?,?)')
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $policies = $repository->getMany(['policy-1', 'policy-2']);

        // Assert
        expect($policies)->toHaveCount(2)
            ->and($policies)->toHaveKey('policy-1')
            ->and($policies)->toHaveKey('policy-2');
    });

    test('getMany() uses cached policies when available', function (): void {
        // Arrange
        $policy1Data = ['name' => 'policy-1', 'rules' => []];
        $policy2Data = ['name' => 'policy-2', 'rules' => []];
        $policy3Data = ['name' => 'policy-3', 'rules' => []];

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with([]);
        $stmt->shouldReceive('fetch')
            ->times(4)
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(
                ['name' => 'policy-1', 'definition' => json_encode($policy1Data)],
                ['name' => 'policy-2', 'definition' => json_encode($policy2Data)],
                ['name' => 'policy-3', 'definition' => json_encode($policy3Data)],
                false,
            );

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $repository->all();
        // Cache all policies
        $policies = $repository->getMany(['policy-1', 'policy-3']); // Use cache

        // Assert
        expect($policies)->toHaveCount(2)
            ->and($policies)->toHaveKey('policy-1')
            ->and($policies)->toHaveKey('policy-3')
            ->and($policies)->not->toHaveKey('policy-2');
    });

    test('getMany() applies additional conditions when not using cache', function (): void {
        // Arrange
        $policyData = ['name' => 'policy-1', 'rules' => []];

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['tenant-789', 'policy-1']);
        $stmt->shouldReceive('fetch')
            ->times(2)
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(
                ['name' => 'policy-1', 'definition' => json_encode($policyData)],
                false,
            );

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->with('SELECT * FROM policies WHERE tenant_id = ? AND name IN (?)')
            ->andReturn($stmt);

        $repository = new DatabaseRepository(
            $pdo,
            'policies',
            'name',
            'definition',
            ['tenant_id' => 'tenant-789'],
        );

        // Act
        $policies = $repository->getMany(['policy-1']);

        // Assert
        expect($policies)->toHaveCount(1);
    });

    test('getMany() handles single policy request', function (): void {
        // Arrange
        $policyData = ['name' => 'single-policy', 'rules' => []];

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['single-policy']);
        $stmt->shouldReceive('fetch')
            ->times(2)
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(
                ['name' => 'single-policy', 'definition' => json_encode($policyData)],
                false,
            );

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->with('SELECT * FROM policies WHERE 1=1 AND name IN (?)')
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $policies = $repository->getMany(['single-policy']);

        // Assert
        expect($policies)->toHaveCount(1)
            ->and($policies)->toHaveKey('single-policy');
    });
});

describe('DatabaseRepository parseDefinition() method', function (): void {
    test('parseDefinition() handles array definition', function (): void {
        // Arrange
        $policyData = [
            'name' => 'array-policy',
            'description' => 'From array',
            'rules' => [
                ['path' => '/api', 'effect' => 'allow'],
            ],
        ];

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['array-policy']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'array-policy', 'definition' => $policyData]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $policy = $repository->get('array-policy');

        // Assert
        expect($policy)->toBeInstanceOf(Policy::class)
            ->and($policy->getName())->toBe('array-policy')
            ->and($policy->getDescription())->toBe('From array');
    });

    test('parseDefinition() handles valid JSON string', function (): void {
        // Arrange
        $policyData = [
            'name' => 'json-policy',
            'description' => 'From JSON',
            'rules' => [],
        ];
        $jsonString = json_encode($policyData);

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['json-policy']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'json-policy', 'definition' => $jsonString]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $policy = $repository->get('json-policy');

        // Assert
        expect($policy)->toBeInstanceOf(Policy::class)
            ->and($policy->getName())->toBe('json-policy');
    });

    test('parseDefinition() throws InvalidDefinitionTypeException for non-string non-array', function (): void {
        // Arrange
        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['invalid-policy']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'invalid-policy', 'definition' => 12_345]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act & Assert
        expect(fn (): Policy => $repository->get('invalid-policy'))
            ->toThrow(InvalidDefinitionTypeException::class);
    });

    test('parseDefinition() throws InvalidDefinitionTypeException for boolean', function (): void {
        // Arrange
        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['bool-policy']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'bool-policy', 'definition' => true]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act & Assert
        expect(fn (): Policy => $repository->get('bool-policy'))
            ->toThrow(InvalidDefinitionTypeException::class);
    });

    test('parseDefinition() throws InvalidJsonDefinitionException for invalid JSON', function (): void {
        // Arrange
        $invalidJson = '{invalid json syntax}';

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['bad-json']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'bad-json', 'definition' => $invalidJson]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act & Assert
        expect(fn (): Policy => $repository->get('bad-json'))
            ->toThrow(InvalidJsonDefinitionException::class);
    });

    test('parseDefinition() throws InvalidJsonDefinitionException for malformed JSON', function (): void {
        // Arrange
        $malformedJson = '{"name": "test", "rules": [}';

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['malformed']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'malformed', 'definition' => $malformedJson]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act & Assert
        expect(fn (): Policy => $repository->get('malformed'))
            ->toThrow(InvalidJsonDefinitionException::class);
    });

    test('parseDefinition() throws InvalidParsedDefinitionException for JSON string value', function (): void {
        // Arrange
        $jsonString = '"just a string"';

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['string-value']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'string-value', 'definition' => $jsonString]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act & Assert
        expect(fn (): Policy => $repository->get('string-value'))
            ->toThrow(InvalidParsedDefinitionException::class);
    });

    test('parseDefinition() throws InvalidParsedDefinitionException for JSON number value', function (): void {
        // Arrange
        $jsonNumber = '42';

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['number-value']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'number-value', 'definition' => $jsonNumber]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act & Assert
        expect(fn (): Policy => $repository->get('number-value'))
            ->toThrow(InvalidParsedDefinitionException::class);
    });

    test('parseDefinition() throws InvalidParsedDefinitionException for JSON null value', function (): void {
        // Arrange
        $jsonNull = 'null';

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['null-value']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'null-value', 'definition' => $jsonNull]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act & Assert
        expect(fn (): Policy => $repository->get('null-value'))
            ->toThrow(InvalidParsedDefinitionException::class);
    });
});

describe('DatabaseRepository edge cases', function (): void {
    test('repository handles complex policy with multiple rules', function (): void {
        // Arrange
        $policyData = [
            'name' => 'complex-policy',
            'description' => 'Complex access rules',
            'rules' => [
                ['path' => '/api/users', 'effect' => 'allow', 'capabilities' => ['read']],
                ['path' => '/api/admin', 'effect' => 'deny', 'capabilities' => ['delete']],
                ['path' => '/api/posts', 'effect' => 'allow'],
            ],
        ];
        $jsonDefinition = json_encode($policyData);

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['complex-policy']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'complex-policy', 'definition' => $jsonDefinition]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $policy = $repository->get('complex-policy');

        // Assert
        expect($policy->getRules())->toHaveCount(3)
            ->and($policy->getDescription())->toBe('Complex access rules');
    });

    test('repository handles policy with empty rules array', function (): void {
        // Arrange
        $policyData = ['name' => 'empty-rules', 'description' => 'No rules', 'rules' => []];
        $jsonDefinition = json_encode($policyData);

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['empty-rules']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'empty-rules', 'definition' => $jsonDefinition]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $policy = $repository->get('empty-rules');

        // Assert
        expect($policy->getRules())->toBe([])
            ->and($policy->getDescription())->toBe('No rules');
    });

    test('repository handles policy without description', function (): void {
        // Arrange
        $policyData = ['name' => 'no-desc', 'rules' => []];
        $jsonDefinition = json_encode($policyData);

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['no-desc']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'no-desc', 'definition' => $jsonDefinition]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $policy = $repository->get('no-desc');

        // Assert
        expect($policy->getDescription())->toBe('');
    });

    test('has() and get() are consistent', function (): void {
        // Arrange
        $policyData = ['name' => 'consistent', 'rules' => []];
        $jsonDefinition = json_encode($policyData);

        $hasStmt = Mockery::mock(PDOStatement::class);
        $hasStmt->shouldReceive('execute')
            ->once()
            ->with(['consistent']);
        $hasStmt->shouldReceive('fetchColumn')
            ->once()
            ->andReturn(1);

        $getStmt = Mockery::mock(PDOStatement::class);
        $getStmt->shouldReceive('execute')
            ->once()
            ->with(['consistent']);
        $getStmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'consistent', 'definition' => $jsonDefinition]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->times(2)
            ->andReturn($hasStmt, $getStmt);

        $repository = new DatabaseRepository($pdo);

        // Act & Assert
        expect($repository->has('consistent'))->toBeTrue();
        expect(fn (): Policy => $repository->get('consistent'))->not->toThrow(PolicyNotFoundException::class);
    });

    test('repository handles multiple conditions with IN query', function (): void {
        // Arrange
        $policy1Data = ['name' => 'p1', 'rules' => []];
        $policy2Data = ['name' => 'p2', 'rules' => []];
        $policy3Data = ['name' => 'p3', 'rules' => []];

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['org-123', true, 'p1', 'p2', 'p3']);
        $stmt->shouldReceive('fetch')
            ->times(4)
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(
                ['name' => 'p1', 'definition' => json_encode($policy1Data)],
                ['name' => 'p2', 'definition' => json_encode($policy2Data)],
                ['name' => 'p3', 'definition' => json_encode($policy3Data)],
                false,
            );

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->with('SELECT * FROM policies WHERE org_id = ? AND active = ? AND name IN (?,?,?)')
            ->andReturn($stmt);

        $repository = new DatabaseRepository(
            $pdo,
            'policies',
            'name',
            'definition',
            ['org_id' => 'org-123', 'active' => true],
        );

        // Act
        $policies = $repository->getMany(['p1', 'p2', 'p3']);

        // Assert
        expect($policies)->toHaveCount(3);
    });

    test('repository handles whitespace in JSON', function (): void {
        // Arrange
        $jsonWithWhitespace = <<<'JSON'
        {
            "name": "formatted-policy",
            "description": "Pretty printed",
            "rules": [
                {
                    "path": "/api/test",
                    "effect": "allow"
                }
            ]
        }
        JSON;

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['formatted-policy']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'formatted-policy', 'definition' => $jsonWithWhitespace]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $policy = $repository->get('formatted-policy');

        // Assert
        expect($policy->getName())->toBe('formatted-policy')
            ->and($policy->getDescription())->toBe('Pretty printed')
            ->and($policy->getRules())->toHaveCount(1);
    });

    test('repository handles UTF-8 characters in JSON', function (): void {
        // Arrange
        $policyData = [
            'name' => 'utf8-policy',
            'description' => 'Policy with émojis 🎉 and ùnicode',
            'rules' => [],
        ];
        $jsonDefinition = json_encode($policyData);

        $stmt = Mockery::mock(PDOStatement::class);
        $stmt->shouldReceive('execute')
            ->once()
            ->with(['utf8-policy']);
        $stmt->shouldReceive('fetch')
            ->once()
            ->with(PDO::FETCH_ASSOC)
            ->andReturn(['name' => 'utf8-policy', 'definition' => $jsonDefinition]);

        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')
            ->once()
            ->andReturn($stmt);

        $repository = new DatabaseRepository($pdo);

        // Act
        $policy = $repository->get('utf8-policy');

        // Assert
        expect($policy->getDescription())->toBe('Policy with émojis 🎉 and ùnicode');
    });
});
