<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\Exception\EmptyChainedRepositoryException;
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Repository\ChainedRepository;
use Cline\Arbiter\Repository\PolicyRepositoryInterface;

describe('ChainedRepository constructor', function (): void {
    test('constructor throws EmptyChainedRepositoryException when given empty array', function (): void {
        expect(fn (): ChainedRepository => new ChainedRepository([]))
            ->toThrow(EmptyChainedRepositoryException::class, 'ChainedRepository requires at least one repository');
    });

    test('constructor accepts single repository', function (): void {
        // Arrange
        $repository = mock(PolicyRepositoryInterface::class);

        // Act
        $chainedRepository = new ChainedRepository([$repository]);

        // Assert
        expect($chainedRepository)->toBeInstanceOf(ChainedRepository::class);
    });

    test('constructor accepts multiple repositories', function (): void {
        // Arrange
        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);
        $repository3 = mock(PolicyRepositoryInterface::class);

        // Act
        $chainedRepository = new ChainedRepository([$repository1, $repository2, $repository3]);

        // Assert
        expect($chainedRepository)->toBeInstanceOf(ChainedRepository::class);
    });
});

describe('ChainedRepository get() fallback chain behavior', function (): void {
    test('get() returns policy from first repository when it has the policy', function (): void {
        // Arrange
        $policy = Policy::create('test-policy');
        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('has')->with('test-policy')->andReturn(true);
        $repository1->expects('get')->with('test-policy')->andReturn($policy);
        $repository2->expects('has')->never();
        $repository2->expects('get')->never();

        $chainedRepository = new ChainedRepository([$repository1, $repository2]);

        // Act
        $result = $chainedRepository->get('test-policy');

        // Assert
        expect($result)->toBe($policy);
    });

    test('get() falls back to second repository when first does not have policy', function (): void {
        // Arrange
        $policy = Policy::create('test-policy');
        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('has')->with('test-policy')->andReturn(false);
        $repository2->expects('has')->with('test-policy')->andReturn(true);
        $repository2->expects('get')->with('test-policy')->andReturn($policy);

        $chainedRepository = new ChainedRepository([$repository1, $repository2]);

        // Act
        $result = $chainedRepository->get('test-policy');

        // Assert
        expect($result)->toBe($policy);
    });

    test('get() checks all repositories in order until finding policy', function (): void {
        // Arrange
        $policy = Policy::create('test-policy');
        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);
        $repository3 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('has')->with('test-policy')->andReturn(false);
        $repository2->expects('has')->with('test-policy')->andReturn(false);
        $repository3->expects('has')->with('test-policy')->andReturn(true);
        $repository3->expects('get')->with('test-policy')->andReturn($policy);

        $chainedRepository = new ChainedRepository([$repository1, $repository2, $repository3]);

        // Act
        $result = $chainedRepository->get('test-policy');

        // Assert
        expect($result)->toBe($policy);
    });

    test('get() throws PolicyNotFoundException when no repository has policy', function (): void {
        // Arrange
        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('has')->with('missing-policy')->andReturn(false);
        $repository2->expects('has')->with('missing-policy')->andReturn(false);

        $chainedRepository = new ChainedRepository([$repository1, $repository2]);

        // Act & Assert
        expect(fn (): Policy => $chainedRepository->get('missing-policy'))
            ->toThrow(PolicyNotFoundException::class, 'Policy not found: missing-policy');
    });

    test('get() respects repository priority order', function (): void {
        // Arrange
        $policy1 = Policy::create('duplicate-policy')->description('From first repository');
        $policy2 = Policy::create('duplicate-policy')->description('From second repository');

        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('has')->with('duplicate-policy')->andReturn(true);
        $repository1->expects('get')->with('duplicate-policy')->andReturn($policy1);
        $repository2->expects('has')->never();
        $repository2->expects('get')->never();

        $chainedRepository = new ChainedRepository([$repository1, $repository2]);

        // Act
        $result = $chainedRepository->get('duplicate-policy');

        // Assert
        expect($result)->toBe($policy1)
            ->and($result->getDescription())->toBe('From first repository');
    });
});

describe('ChainedRepository has() checking multiple repositories', function (): void {
    test('has() returns true when first repository has policy', function (): void {
        // Arrange
        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);

        // array_any does NOT short-circuit - it checks ALL repositories
        $repository1->allows('has')->with('test-policy')->andReturn(true);
        $repository2->allows('has')->with('test-policy')->andReturn(false);

        $chainedRepository = new ChainedRepository([$repository1, $repository2]);

        // Act
        $result = $chainedRepository->has('test-policy');

        // Assert
        expect($result)->toBeTrue();
    });

    test('has() returns true when second repository has policy', function (): void {
        // Arrange
        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);

        $repository1->allows('has')->with('test-policy')->andReturn(false);
        $repository2->allows('has')->with('test-policy')->andReturn(true);

        $chainedRepository = new ChainedRepository([$repository1, $repository2]);

        // Act
        $result = $chainedRepository->has('test-policy');

        // Assert
        expect($result)->toBeTrue();
    });

    test('has() returns true when any repository in chain has policy', function (): void {
        // Arrange
        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);
        $repository3 = mock(PolicyRepositoryInterface::class);

        // array_any checks all elements
        $repository1->allows('has')->with('test-policy')->andReturn(false);
        $repository2->allows('has')->with('test-policy')->andReturn(true);
        $repository3->allows('has')->with('test-policy')->andReturn(false);

        $chainedRepository = new ChainedRepository([$repository1, $repository2, $repository3]);

        // Act
        $result = $chainedRepository->has('test-policy');

        // Assert
        expect($result)->toBeTrue();
    });

    test('has() returns false when no repository has policy', function (): void {
        // Arrange
        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);
        $repository3 = mock(PolicyRepositoryInterface::class);

        $repository1->allows('has')->with('missing-policy')->andReturn(false);
        $repository2->allows('has')->with('missing-policy')->andReturn(false);
        $repository3->allows('has')->with('missing-policy')->andReturn(false);

        $chainedRepository = new ChainedRepository([$repository1, $repository2, $repository3]);

        // Act
        $result = $chainedRepository->has('missing-policy');

        // Assert
        expect($result)->toBeFalse();
    });

    test('has() checks all repositories even when first matches', function (): void {
        // Arrange
        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);

        // array_any does NOT short-circuit - both repositories are checked
        $repository1->allows('has')->with('test-policy')->andReturn(true);
        $repository2->allows('has')->with('test-policy')->andReturn(true);

        $chainedRepository = new ChainedRepository([$repository1, $repository2]);

        // Act
        $result = $chainedRepository->has('test-policy');

        // Assert
        expect($result)->toBeTrue();
    });
});

describe('ChainedRepository all() merging with precedence', function (): void {
    test('all() returns policies from single repository', function (): void {
        // Arrange
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');

        $repository = mock(PolicyRepositoryInterface::class);
        $repository->expects('all')->andReturn([
            'policy-1' => $policy1,
            'policy-2' => $policy2,
        ]);

        $chainedRepository = new ChainedRepository([$repository]);

        // Act
        $result = $chainedRepository->all();

        // Assert
        expect($result)->toBe([
            'policy-1' => $policy1,
            'policy-2' => $policy2,
        ]);
    });

    test('all() merges policies from multiple repositories', function (): void {
        // Arrange
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');
        $policy3 = Policy::create('policy-3');

        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('all')->andReturn(['policy-1' => $policy1]);
        $repository2->expects('all')->andReturn([
            'policy-2' => $policy2,
            'policy-3' => $policy3,
        ]);

        $chainedRepository = new ChainedRepository([$repository1, $repository2]);

        // Act
        $result = $chainedRepository->all();

        // Assert
        expect($result)->toHaveCount(3)
            ->and($result)->toHaveKeys(['policy-1', 'policy-2', 'policy-3'])
            ->and($result['policy-1'])->toBe($policy1)
            ->and($result['policy-2'])->toBe($policy2)
            ->and($result['policy-3'])->toBe($policy3);
    });

    test('all() gives precedence to earlier repositories for duplicate names', function (): void {
        // Arrange
        $policy1 = Policy::create('duplicate')->description('From first repository');
        $policy2 = Policy::create('duplicate')->description('From second repository');

        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('all')->andReturn(['duplicate' => $policy1]);
        $repository2->expects('all')->andReturn(['duplicate' => $policy2]);

        $chainedRepository = new ChainedRepository([$repository1, $repository2]);

        // Act
        $result = $chainedRepository->all();

        // Assert
        expect($result)->toHaveCount(1)
            ->and($result['duplicate'])->toBe($policy1)
            ->and($result['duplicate']->getDescription())->toBe('From first repository');
    });

    test('all() correctly handles three repositories with overlapping policies', function (): void {
        // Arrange
        $policy1 = Policy::create('unique-1');
        $policy2Repo1 = Policy::create('duplicate')->description('From repo 1');
        $policy2Repo2 = Policy::create('duplicate')->description('From repo 2');
        $policy3 = Policy::create('unique-2');
        $policy4Repo2 = Policy::create('another-duplicate')->description('From repo 2');
        $policy4Repo3 = Policy::create('another-duplicate')->description('From repo 3');
        $policy5 = Policy::create('unique-3');

        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);
        $repository3 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('all')->andReturn([
            'unique-1' => $policy1,
            'duplicate' => $policy2Repo1,
        ]);
        $repository2->expects('all')->andReturn([
            'duplicate' => $policy2Repo2,
            'unique-2' => $policy3,
            'another-duplicate' => $policy4Repo2,
        ]);
        $repository3->expects('all')->andReturn([
            'another-duplicate' => $policy4Repo3,
            'unique-3' => $policy5,
        ]);

        $chainedRepository = new ChainedRepository([$repository1, $repository2, $repository3]);

        // Act
        $result = $chainedRepository->all();

        // Assert
        expect($result)->toHaveCount(5)
            ->and($result)->toHaveKeys(['unique-1', 'duplicate', 'unique-2', 'another-duplicate', 'unique-3'])
            ->and($result['duplicate'])->toBe($policy2Repo1)
            ->and($result['another-duplicate'])->toBe($policy4Repo2);
    });

    test('all() returns empty array when all repositories are empty', function (): void {
        // Arrange
        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('all')->andReturn([]);
        $repository2->expects('all')->andReturn([]);

        $chainedRepository = new ChainedRepository([$repository1, $repository2]);

        // Act
        $result = $chainedRepository->all();

        // Assert
        expect($result)->toBe([]);
    });

    test('all() handles mix of empty and non-empty repositories', function (): void {
        // Arrange
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');

        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);
        $repository3 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('all')->andReturn(['policy-1' => $policy1]);
        $repository2->expects('all')->andReturn([]);
        $repository3->expects('all')->andReturn(['policy-2' => $policy2]);

        $chainedRepository = new ChainedRepository([$repository1, $repository2, $repository3]);

        // Act
        $result = $chainedRepository->all();

        // Assert
        expect($result)->toHaveCount(2)
            ->and($result)->toHaveKeys(['policy-1', 'policy-2']);
    });
});

describe('ChainedRepository getMany() with partial matches', function (): void {
    test('getMany() returns all policies from first repository when it has them all', function (): void {
        // Arrange
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');

        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('getMany')->with(['policy-1', 'policy-2'])->andReturn([
            'policy-1' => $policy1,
            'policy-2' => $policy2,
        ]);
        $repository2->expects('getMany')->never();

        $chainedRepository = new ChainedRepository([$repository1, $repository2]);

        // Act
        $result = $chainedRepository->getMany(['policy-1', 'policy-2']);

        // Assert
        expect($result)->toBe([
            'policy-1' => $policy1,
            'policy-2' => $policy2,
        ]);
    });

    test('getMany() falls back to second repository for missing policies', function (): void {
        // Arrange
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');

        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('getMany')->with(['policy-1', 'policy-2'])->andReturn([
            'policy-1' => $policy1,
        ]);
        // array_diff returns [1 => 'policy-2'] (preserves keys)
        $repository2->expects('getMany')->with([1 => 'policy-2'])->andReturn([
            'policy-2' => $policy2,
        ]);

        $chainedRepository = new ChainedRepository([$repository1, $repository2]);

        // Act
        $result = $chainedRepository->getMany(['policy-1', 'policy-2']);

        // Assert
        expect($result)->toHaveCount(2)
            ->and($result)->toHaveKeys(['policy-1', 'policy-2'])
            ->and($result['policy-1'])->toBe($policy1)
            ->and($result['policy-2'])->toBe($policy2);
    });

    test('getMany() distributes requests across multiple repositories', function (): void {
        // Arrange
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');
        $policy3 = Policy::create('policy-3');

        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);
        $repository3 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('getMany')->with(['policy-1', 'policy-2', 'policy-3'])->andReturn([
            'policy-1' => $policy1,
        ]);
        // array_diff returns [1 => 'policy-2', 2 => 'policy-3'] (preserves keys)
        $repository2->expects('getMany')->with([1 => 'policy-2', 2 => 'policy-3'])->andReturn([
            'policy-2' => $policy2,
        ]);
        // array_diff returns [2 => 'policy-3'] (preserves keys)
        $repository3->expects('getMany')->with([2 => 'policy-3'])->andReturn([
            'policy-3' => $policy3,
        ]);

        $chainedRepository = new ChainedRepository([$repository1, $repository2, $repository3]);

        // Act
        $result = $chainedRepository->getMany(['policy-1', 'policy-2', 'policy-3']);

        // Assert
        expect($result)->toHaveCount(3)
            ->and($result['policy-1'])->toBe($policy1)
            ->and($result['policy-2'])->toBe($policy2)
            ->and($result['policy-3'])->toBe($policy3);
    });

    test('getMany() returns empty array when requesting empty array', function (): void {
        // Arrange
        $repository = mock(PolicyRepositoryInterface::class);
        $repository->expects('getMany')->never();

        $chainedRepository = new ChainedRepository([$repository]);

        // Act
        $result = $chainedRepository->getMany([]);

        // Assert
        expect($result)->toBe([]);
    });

    test('getMany() returns partial results when some policies are not found', function (): void {
        // Arrange
        $policy1 = Policy::create('policy-1');

        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('getMany')->with(['policy-1', 'missing'])->andReturn([
            'policy-1' => $policy1,
        ]);
        // array_diff returns [1 => 'missing'] (preserves keys)
        $repository2->expects('getMany')->with([1 => 'missing'])->andReturn([]);

        $chainedRepository = new ChainedRepository([$repository1, $repository2]);

        // Act
        $result = $chainedRepository->getMany(['policy-1', 'missing']);

        // Assert
        expect($result)->toHaveCount(1)
            ->and($result)->toHaveKey('policy-1')
            ->and($result)->not->toHaveKey('missing');
    });

    test('getMany() respects repository priority for duplicate names', function (): void {
        // Arrange
        $policy1Repo1 = Policy::create('duplicate')->description('From first repository');
        $policy1Repo2 = Policy::create('duplicate')->description('From second repository');

        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('getMany')->with(['duplicate'])->andReturn([
            'duplicate' => $policy1Repo1,
        ]);
        $repository2->expects('getMany')->never();

        $chainedRepository = new ChainedRepository([$repository1, $repository2]);

        // Act
        $result = $chainedRepository->getMany(['duplicate']);

        // Assert
        expect($result)->toHaveCount(1)
            ->and($result['duplicate'])->toBe($policy1Repo1)
            ->and($result['duplicate']->getDescription())->toBe('From first repository');
    });

    test('getMany() stops checking repositories once all policies are found', function (): void {
        // Arrange
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');

        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);
        $repository3 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('getMany')->with(['policy-1', 'policy-2'])->andReturn([
            'policy-1' => $policy1,
        ]);
        // array_diff returns [1 => 'policy-2'] (preserves keys)
        $repository2->expects('getMany')->with([1 => 'policy-2'])->andReturn([
            'policy-2' => $policy2,
        ]);
        // Repository 3 should never be called since all policies were found
        $repository3->expects('getMany')->never();

        $chainedRepository = new ChainedRepository([$repository1, $repository2, $repository3]);

        // Act
        $result = $chainedRepository->getMany(['policy-1', 'policy-2']);

        // Assert
        expect($result)->toHaveCount(2);
    });

    test('getMany() handles complex partial matching scenario', function (): void {
        // Arrange
        $policy1 = Policy::create('found-in-first');
        $policy2 = Policy::create('found-in-second');
        $policy3 = Policy::create('found-in-third');
        $policy4 = Policy::create('also-in-first');

        $repository1 = mock(PolicyRepositoryInterface::class);
        $repository2 = mock(PolicyRepositoryInterface::class);
        $repository3 = mock(PolicyRepositoryInterface::class);

        $repository1->expects('getMany')
            ->with(['found-in-first', 'found-in-second', 'found-in-third', 'also-in-first', 'missing'])
            ->andReturn([
                'found-in-first' => $policy1,
                'also-in-first' => $policy4,
            ]);
        // array_diff returns [1 => 'found-in-second', 2 => 'found-in-third', 4 => 'missing'] (preserves keys)
        $repository2->expects('getMany')
            ->with([1 => 'found-in-second', 2 => 'found-in-third', 4 => 'missing'])
            ->andReturn([
                'found-in-second' => $policy2,
            ]);
        // array_diff returns [2 => 'found-in-third', 4 => 'missing'] (preserves keys)
        $repository3->expects('getMany')
            ->with([2 => 'found-in-third', 4 => 'missing'])
            ->andReturn([
                'found-in-third' => $policy3,
            ]);

        $chainedRepository = new ChainedRepository([$repository1, $repository2, $repository3]);

        // Act
        $result = $chainedRepository->getMany([
            'found-in-first',
            'found-in-second',
            'found-in-third',
            'also-in-first',
            'missing',
        ]);

        // Assert
        expect($result)->toHaveCount(4)
            ->and($result)->toHaveKeys(['found-in-first', 'found-in-second', 'found-in-third', 'also-in-first'])
            ->and($result)->not->toHaveKey('missing')
            ->and($result['found-in-first'])->toBe($policy1)
            ->and($result['found-in-second'])->toBe($policy2)
            ->and($result['found-in-third'])->toBe($policy3)
            ->and($result['also-in-first'])->toBe($policy4);
    });
});
