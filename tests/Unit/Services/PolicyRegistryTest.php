<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Repository\PolicyRepositoryInterface;
use Cline\Arbiter\Services\PolicyRegistry;

describe('PolicyRegistry add() method', function (): void {
    test('add() registers policy in memory', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy = Policy::create('test-policy');

        // Act
        $registry->add($policy);

        // Assert
        expect($registry->has('test-policy'))->toBeTrue()
            ->and($registry->get('test-policy'))->toBe($policy);
    });

    test('add() registers multiple policies', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');
        $policy3 = Policy::create('policy-3');

        // Act
        $registry->add($policy1);
        $registry->add($policy2);
        $registry->add($policy3);

        // Assert
        expect($registry->all())->toHaveCount(3)
            ->and($registry->all())->toHaveKey('policy-1')
            ->and($registry->all())->toHaveKey('policy-2')
            ->and($registry->all())->toHaveKey('policy-3');
    });

    test('add() overwrites existing policy with same name', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $originalPolicy = Policy::create('test-policy')->description('Original');
        $updatedPolicy = Policy::create('test-policy')->description('Updated');

        // Act
        $registry->add($originalPolicy);
        $registry->add($updatedPolicy);

        // Assert
        expect($registry->get('test-policy'))->toBe($updatedPolicy)
            ->and($registry->get('test-policy')->getDescription())->toBe('Updated');
    });
});

describe('PolicyRegistry get() method', function (): void {
    test('get() returns policy from memory cache', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy = Policy::create('cached-policy')->description('In memory');

        // Act
        $registry->add($policy);
        $result = $registry->get('cached-policy');

        // Assert
        expect($result)->toBe($policy)
            ->and($result->getName())->toBe('cached-policy')
            ->and($result->getDescription())->toBe('In memory');
    });

    test('get() loads policy from repository when not in cache', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy = Policy::create('repo-policy')->description('From repository');

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('repo-policy')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('repo-policy')
            ->andReturn($policy);

        $registry->setRepository($repository);

        // Act
        $result = $registry->get('repo-policy');

        // Assert
        expect($result)->toBe($policy)
            ->and($result->getName())->toBe('repo-policy');
    });

    test('get() caches policy loaded from repository', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy = Policy::create('cacheable-policy');

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('cacheable-policy')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('cacheable-policy')
            ->andReturn($policy);

        $registry->setRepository($repository);

        // Act
        $result1 = $registry->get('cacheable-policy');
        $result2 = $registry->get('cacheable-policy');

        // Assert
        expect($result1)->toBe($policy)
            ->and($result2)->toBe($policy)
            ->and($registry->all())->toHaveKey('cacheable-policy');
    });

    test('get() prefers memory cache over repository', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $cachedPolicy = Policy::create('test-policy')->description('Cached');
        $repoPolicy = Policy::create('test-policy')->description('Repository');

        $registry->add($cachedPolicy);

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldNotReceive('has');
        $repository->shouldNotReceive('get');

        $registry->setRepository($repository);

        // Act
        $result = $registry->get('test-policy');

        // Assert
        expect($result)->toBe($cachedPolicy)
            ->and($result->getDescription())->toBe('Cached');
    });

    test('get() throws PolicyNotFoundException when policy not found in cache or repository', function (): void {
        // Arrange
        $registry = new PolicyRegistry();

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('nonexistent')
            ->andReturn(false);

        $registry->setRepository($repository);

        // Act & Assert
        expect(fn (): Policy => $registry->get('nonexistent'))
            ->toThrow(PolicyNotFoundException::class, 'Policy not found: nonexistent');
    });

    test('get() throws PolicyNotFoundException when repository not set and policy not in cache', function (): void {
        // Arrange
        $registry = new PolicyRegistry();

        // Act & Assert
        expect(fn (): Policy => $registry->get('missing-policy'))
            ->toThrow(PolicyNotFoundException::class, 'Policy not found: missing-policy');
    });

    test('get() returns correct policy when multiple policies in cache', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy1 = Policy::create('policy-1')->description('First');
        $policy2 = Policy::create('policy-2')->description('Second');
        $policy3 = Policy::create('policy-3')->description('Third');

        // Act
        $registry->add($policy1);
        $registry->add($policy2);
        $registry->add($policy3);

        // Assert
        expect($registry->get('policy-2'))->toBe($policy2)
            ->and($registry->get('policy-2')->getDescription())->toBe('Second');
    });
});

describe('PolicyRegistry has() method', function (): void {
    test('has() returns true when policy exists in cache', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy = Policy::create('cached-policy');

        // Act
        $registry->add($policy);

        // Assert
        expect($registry->has('cached-policy'))->toBeTrue();
    });

    test('has() returns true when policy exists in repository', function (): void {
        // Arrange
        $registry = new PolicyRegistry();

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('repo-policy')
            ->andReturn(true);

        $registry->setRepository($repository);

        // Act & Assert
        expect($registry->has('repo-policy'))->toBeTrue();
    });

    test('has() returns false when policy not found anywhere', function (): void {
        // Arrange
        $registry = new PolicyRegistry();

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('nonexistent')
            ->andReturn(false);

        $registry->setRepository($repository);

        // Act & Assert
        expect($registry->has('nonexistent'))->toBeFalse();
    });

    test('has() returns false when repository not set and policy not in cache', function (): void {
        // Arrange
        $registry = new PolicyRegistry();

        // Act & Assert
        expect($registry->has('missing-policy'))->toBeFalse();
    });

    test('has() checks cache before repository', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy = Policy::create('test-policy');

        $registry->add($policy);

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldNotReceive('has');

        $registry->setRepository($repository);

        // Act & Assert
        expect($registry->has('test-policy'))->toBeTrue();
    });

    test('has() checks repository when not in cache', function (): void {
        // Arrange
        $registry = new PolicyRegistry();

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('repo-only')
            ->andReturn(true);

        $registry->setRepository($repository);

        // Act & Assert
        expect($registry->has('repo-only'))->toBeTrue();
    });

    test('has() and get() are consistent for cached policies', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy = Policy::create('test-policy');

        // Act
        $registry->add($policy);

        // Assert
        if (!$registry->has('test-policy')) {
            return;
        }

        expect(fn (): Policy => $registry->get('test-policy'))->not->toThrow(PolicyNotFoundException::class);
    });

    test('has() and get() are consistent for repository policies', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy = Policy::create('repo-policy');

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->twice()
            ->with('repo-policy')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('repo-policy')
            ->andReturn($policy);

        $registry->setRepository($repository);

        // Act & Assert
        if (!$registry->has('repo-policy')) {
            return;
        }

        expect(fn (): Policy => $registry->get('repo-policy'))->not->toThrow(PolicyNotFoundException::class);
    });
});

describe('PolicyRegistry setRepository() method', function (): void {
    test('setRepository() configures repository for policy loading', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy = Policy::create('test-policy');

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('test-policy')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('test-policy')
            ->andReturn($policy);

        // Act
        $registry->setRepository($repository);
        $result = $registry->get('test-policy');

        // Assert
        expect($result)->toBe($policy);
    });

    test('setRepository() allows repository-backed has() checks', function (): void {
        // Arrange
        $registry = new PolicyRegistry();

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('repo-policy')
            ->andReturn(true);

        // Act
        $registry->setRepository($repository);

        // Assert
        expect($registry->has('repo-policy'))->toBeTrue();
    });

    test('setRepository() can be called after adding policies to cache', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $cachedPolicy = Policy::create('cached-policy');
        $repoPolicy = Policy::create('repo-policy');

        $registry->add($cachedPolicy);

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('repo-policy')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('repo-policy')
            ->andReturn($repoPolicy);

        // Act
        $registry->setRepository($repository);

        // Assert
        expect($registry->get('cached-policy'))->toBe($cachedPolicy)
            ->and($registry->get('repo-policy'))->toBe($repoPolicy);
    });
});

describe('PolicyRegistry all() method', function (): void {
    test('all() returns empty array when no policies registered', function (): void {
        // Arrange
        $registry = new PolicyRegistry();

        // Act
        $result = $registry->all();

        // Assert
        expect($result)->toBe([]);
    });

    test('all() returns all cached policies keyed by name', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');

        // Act
        $registry->add($policy1);
        $registry->add($policy2);

        $result = $registry->all();

        // Assert
        expect($result)->toHaveCount(2)
            ->and($result)->toHaveKey('policy-1', $policy1)
            ->and($result)->toHaveKey('policy-2', $policy2);
    });

    test('all() does not load all policies from repository', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $cachedPolicy = Policy::create('cached-policy');

        $registry->add($cachedPolicy);

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldNotReceive('all');

        $registry->setRepository($repository);

        // Act
        $result = $registry->all();

        // Assert
        expect($result)->toHaveCount(1)
            ->and($result)->toHaveKey('cached-policy');
    });

    test('all() includes policies loaded from repository', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $repoPolicy = Policy::create('repo-policy');

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('repo-policy')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('repo-policy')
            ->andReturn($repoPolicy);

        $registry->setRepository($repository);

        // Act
        $registry->get('repo-policy');
        // Load from repository into cache
        $result = $registry->all();

        // Assert
        expect($result)->toHaveCount(1)
            ->and($result)->toHaveKey('repo-policy', $repoPolicy);
    });

    test('all() returns combination of cached and loaded policies', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $cachedPolicy = Policy::create('cached-policy');
        $repoPolicy = Policy::create('repo-policy');

        $registry->add($cachedPolicy);

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('repo-policy')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('repo-policy')
            ->andReturn($repoPolicy);

        $registry->setRepository($repository);

        // Act
        $registry->get('repo-policy');
        // Load from repository
        $result = $registry->all();

        // Assert
        expect($result)->toHaveCount(2)
            ->and($result)->toHaveKey('cached-policy', $cachedPolicy)
            ->and($result)->toHaveKey('repo-policy', $repoPolicy);
    });

    test('all() returns policies with full data intact', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy1 = Policy::create('admin-policy')->description('Admin access');
        $policy2 = Policy::create('user-policy')->description('User access');

        // Act
        $registry->add($policy1);
        $registry->add($policy2);

        $result = $registry->all();

        // Assert
        expect($result['admin-policy']->getDescription())->toBe('Admin access')
            ->and($result['user-policy']->getDescription())->toBe('User access');
    });
});

describe('PolicyRegistry edge cases', function (): void {
    test('registry handles policy with empty name', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy = Policy::create('');

        // Act
        $registry->add($policy);

        // Assert
        expect($registry->has(''))->toBeTrue()
            ->and($registry->get(''))->toBe($policy);
    });

    test('registry handles repository returning null for has() check', function (): void {
        // Arrange
        $registry = new PolicyRegistry();

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('test-policy')
            ->andReturn(false);

        $registry->setRepository($repository);

        // Act & Assert
        expect($registry->has('test-policy'))->toBeFalse();
    });

    test('registry caches policy only after successful repository load', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy = Policy::create('test-policy');

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('test-policy')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('test-policy')
            ->andReturn($policy);

        $registry->setRepository($repository);

        // Act
        expect($registry->all())->toHaveCount(0); // Not cached yet
        $registry->get('test-policy'); // Load from repository

        // Assert
        expect($registry->all())->toHaveCount(1)
            ->and($registry->all())->toHaveKey('test-policy');
    });

    test('registry handles multiple get() calls for same repository policy', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy = Policy::create('test-policy');

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('test-policy')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('test-policy')
            ->andReturn($policy);

        $registry->setRepository($repository);

        // Act
        $result1 = $registry->get('test-policy');
        $result2 = $registry->get('test-policy');
        $result3 = $registry->get('test-policy');

        // Assert
        expect($result1)->toBe($policy)
            ->and($result2)->toBe($policy)
            ->and($result3)->toBe($policy);
    });

    test('registry preserves policy object identity after caching', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $policy = Policy::create('test-policy');

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('test-policy')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('test-policy')
            ->andReturn($policy);

        $registry->setRepository($repository);

        // Act
        $fromGet = $registry->get('test-policy');
        $fromAll = $registry->all()['test-policy'];

        // Assert
        expect($fromGet)->toBe($policy)
            ->and($fromAll)->toBe($policy)
            ->and($fromGet)->toBe($fromAll);
    });

    test('registry handles repository with no policies matching query', function (): void {
        // Arrange
        $registry = new PolicyRegistry();

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->twice()
            ->with('nonexistent')
            ->andReturn(false);

        $registry->setRepository($repository);

        // Act & Assert
        expect($registry->has('nonexistent'))->toBeFalse();
        expect(fn (): Policy => $registry->get('nonexistent'))
            ->toThrow(PolicyNotFoundException::class, 'Policy not found: nonexistent');
    });

    test('registry isolates cache from repository changes', function (): void {
        // Arrange
        $registry = new PolicyRegistry();
        $originalPolicy = Policy::create('test-policy')->description('Original');

        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('test-policy')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('test-policy')
            ->andReturn($originalPolicy);

        $registry->setRepository($repository);

        // Act
        $result1 = $registry->get('test-policy');

        // Repository would return different policy now, but we should get cached version
        $result2 = $registry->get('test-policy');

        // Assert
        expect($result1)->toBe($originalPolicy)
            ->and($result2)->toBe($originalPolicy)
            ->and($result1)->toBe($result2);
    });
});
