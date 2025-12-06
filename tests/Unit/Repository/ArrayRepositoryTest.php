<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\Capability;
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Repository\ArrayRepository;
use Cline\Arbiter\Rule;

describe('ArrayRepository constructor', function (): void {
    test('constructor creates empty repository', function (): void {
        $repository = new ArrayRepository();

        expect($repository->all())->toBe([]);
    });

    test('constructor accepts array of policies', function (): void {
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');

        $repository = new ArrayRepository([$policy1, $policy2]);

        expect($repository->all())->toBe([
            'policy-1' => $policy1,
            'policy-2' => $policy2,
        ]);
    });

    test('constructor keys policies by name', function (): void {
        $policy = Policy::create('user-policy')->description('Test policy');

        $repository = new ArrayRepository([$policy]);

        expect($repository->all())->toHaveKey('user-policy')
            ->and($repository->all()['user-policy'])->toBe($policy);
    });

    test('constructor handles multiple policies with different names', function (): void {
        $policies = [
            Policy::create('admin-policy')->description('Admin access'),
            Policy::create('user-policy')->description('User access'),
            Policy::create('guest-policy')->description('Guest access'),
        ];

        $repository = new ArrayRepository($policies);

        expect($repository->all())->toHaveCount(3)
            ->and($repository->all())->toHaveKeys(['admin-policy', 'user-policy', 'guest-policy']);
    });
});

describe('ArrayRepository get() method', function (): void {
    test('get() returns policy by name', function (): void {
        $policy = Policy::create('user-policy')->description('Test policy');
        $repository = new ArrayRepository([$policy]);

        $result = $repository->get('user-policy');

        expect($result)->toBe($policy)
            ->and($result->getName())->toBe('user-policy')
            ->and($result->getDescription())->toBe('Test policy');
    });

    test('get() throws PolicyNotFoundException when policy does not exist', function (): void {
        $repository = new ArrayRepository();

        expect(fn (): Policy => $repository->get('nonexistent'))
            ->toThrow(PolicyNotFoundException::class, 'Policy not found: nonexistent');
    });

    test('get() throws PolicyNotFoundException with correct policy name in message', function (): void {
        $policy1 = Policy::create('policy-1');
        $repository = new ArrayRepository([$policy1]);

        expect(fn (): Policy => $repository->get('policy-2'))
            ->toThrow(PolicyNotFoundException::class, 'Policy not found: policy-2');
    });

    test('get() returns correct policy when multiple exist', function (): void {
        $policy1 = Policy::create('policy-1')->description('First policy');
        $policy2 = Policy::create('policy-2')->description('Second policy');
        $policy3 = Policy::create('policy-3')->description('Third policy');

        $repository = new ArrayRepository([$policy1, $policy2, $policy3]);

        expect($repository->get('policy-2'))->toBe($policy2)
            ->and($repository->get('policy-2')->getDescription())->toBe('Second policy');
    });
});

describe('ArrayRepository has() method', function (): void {
    test('has() returns true when policy exists', function (): void {
        $policy = Policy::create('user-policy');
        $repository = new ArrayRepository([$policy]);

        expect($repository->has('user-policy'))->toBeTrue();
    });

    test('has() returns false when policy does not exist', function (): void {
        $repository = new ArrayRepository();

        expect($repository->has('nonexistent'))->toBeFalse();
    });

    test('has() returns false when repository is empty', function (): void {
        $repository = new ArrayRepository([]);

        expect($repository->has('any-policy'))->toBeFalse();
    });

    test('has() checks existence correctly with multiple policies', function (): void {
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');

        $repository = new ArrayRepository([$policy1, $policy2]);

        expect($repository->has('policy-1'))->toBeTrue()
            ->and($repository->has('policy-2'))->toBeTrue()
            ->and($repository->has('policy-3'))->toBeFalse();
    });
});

describe('ArrayRepository all() method', function (): void {
    test('all() returns empty array when repository is empty', function (): void {
        $repository = new ArrayRepository();

        expect($repository->all())->toBe([]);
    });

    test('all() returns all policies keyed by name', function (): void {
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');

        $repository = new ArrayRepository([$policy1, $policy2]);

        $all = $repository->all();

        expect($all)->toHaveCount(2)
            ->and($all)->toHaveKey('policy-1', $policy1)
            ->and($all)->toHaveKey('policy-2', $policy2);
    });

    test('all() returns policies with their full data', function (): void {
        $policy1 = Policy::create('admin-policy')
            ->description('Admin access')
            ->addRule(Rule::allow('/api/admin'));
        $policy2 = Policy::create('user-policy')
            ->description('User access')
            ->addRule(Rule::allow('/api/users'));

        $repository = new ArrayRepository([$policy1, $policy2]);

        $all = $repository->all();

        expect($all['admin-policy']->getDescription())->toBe('Admin access')
            ->and($all['admin-policy']->getRules())->toHaveCount(1)
            ->and($all['user-policy']->getDescription())->toBe('User access')
            ->and($all['user-policy']->getRules())->toHaveCount(1);
    });
});

describe('ArrayRepository getMany() method', function (): void {
    test('getMany() returns multiple policies by name', function (): void {
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');
        $policy3 = Policy::create('policy-3');

        $repository = new ArrayRepository([$policy1, $policy2, $policy3]);

        $result = $repository->getMany(['policy-1', 'policy-3']);

        expect($result)->toHaveCount(2)
            ->and($result)->toHaveKey('policy-1', $policy1)
            ->and($result)->toHaveKey('policy-3', $policy3)
            ->and($result)->not->toHaveKey('policy-2');
    });

    test('getMany() returns empty array when given empty array', function (): void {
        $policy = Policy::create('policy-1');
        $repository = new ArrayRepository([$policy]);

        $result = $repository->getMany([]);

        expect($result)->toBe([]);
    });

    test('getMany() throws PolicyNotFoundException when single policy missing', function (): void {
        $policy1 = Policy::create('policy-1');
        $repository = new ArrayRepository([$policy1]);

        expect(fn (): array => $repository->getMany(['policy-1', 'nonexistent']))
            ->toThrow(PolicyNotFoundException::class, 'Policies not found: nonexistent');
    });

    test('getMany() throws PolicyNotFoundException when multiple policies missing', function (): void {
        $policy1 = Policy::create('policy-1');
        $repository = new ArrayRepository([$policy1]);

        expect(fn (): array => $repository->getMany(['missing-1', 'missing-2']))
            ->toThrow(PolicyNotFoundException::class, 'Policies not found: missing-1, missing-2');
    });

    test('getMany() throws exception listing all missing policies', function (): void {
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');
        $repository = new ArrayRepository([$policy1, $policy2]);

        expect(fn (): array => $repository->getMany(['policy-1', 'missing-1', 'policy-2', 'missing-2', 'missing-3']))
            ->toThrow(PolicyNotFoundException::class, 'Policies not found: missing-1, missing-2, missing-3');
    });

    test('getMany() returns policies in keyed array format', function (): void {
        $policy1 = Policy::create('admin-policy')->description('Admin');
        $policy2 = Policy::create('user-policy')->description('User');

        $repository = new ArrayRepository([$policy1, $policy2]);

        $result = $repository->getMany(['admin-policy', 'user-policy']);

        expect($result)->toBeArray()
            ->and($result['admin-policy'])->toBe($policy1)
            ->and($result['user-policy'])->toBe($policy2);
    });

    test('getMany() returns single policy when only one requested', function (): void {
        $policy1 = Policy::create('policy-1');
        $policy2 = Policy::create('policy-2');

        $repository = new ArrayRepository([$policy1, $policy2]);

        $result = $repository->getMany(['policy-1']);

        expect($result)->toHaveCount(1)
            ->and($result)->toHaveKey('policy-1', $policy1);
    });
});

describe('ArrayRepository edge cases', function (): void {
    test('repository handles policies with complex rules', function (): void {
        $policy = Policy::create('complex-policy')
            ->description('Complex policy with multiple rules')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read))
            ->addRule(Rule::deny('/api/admin')->capabilities(Capability::Delete))
            ->addRule(Rule::allow('/api/posts')->when('role', 'editor'));

        $repository = new ArrayRepository([$policy]);

        $retrieved = $repository->get('complex-policy');

        expect($retrieved->getRules())->toHaveCount(3)
            ->and($retrieved->getDescription())->toBe('Complex policy with multiple rules');
    });

    test('repository preserves policy immutability', function (): void {
        $originalPolicy = Policy::create('test-policy')->description('Original');
        $repository = new ArrayRepository([$originalPolicy]);

        $retrieved = $repository->get('test-policy');

        expect($retrieved)->toBe($originalPolicy)
            ->and($retrieved->getName())->toBe('test-policy');
    });

    test('has() and get() are consistent', function (): void {
        $policy = Policy::create('test-policy');
        $repository = new ArrayRepository([$policy]);

        // If has() returns true, get() should succeed
        if ($repository->has('test-policy')) {
            expect(fn (): Policy => $repository->get('test-policy'))->not->toThrow(PolicyNotFoundException::class);
        }

        // If has() returns false, get() should throw
        if ($repository->has('nonexistent')) {
            return;
        }

        expect(fn (): Policy => $repository->get('nonexistent'))->toThrow(PolicyNotFoundException::class);
    });
});
