<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\ArbiterManager;
use Cline\Arbiter\Capability;
use Cline\Arbiter\Conductors\PathEvaluationConductor;
use Cline\Arbiter\Conductors\PolicyEvaluationConductor;
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Facades\Arbiter;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Repository\ArrayRepository;
use Cline\Arbiter\Repository\PolicyRepositoryInterface;
use Cline\Arbiter\Rule;

describe('Facade Accessor', function (): void {
    test('getFacadeAccessor returns correct binding name', function (): void {
        // Arrange
        $reflection = new ReflectionClass(Arbiter::class);
        $method = $reflection->getMethod('getFacadeAccessor');

        // Act
        $accessor = $method->invoke(null);

        // Assert
        expect($accessor)->toBe('arbiter');
    });

    test('facade resolves to ArbiterManager instance', function (): void {
        // Arrange & Act
        $instance = Arbiter::getFacadeRoot();

        // Assert
        expect($instance)->toBeInstanceOf(ArbiterManager::class);
    });

    test('facade maintains singleton behavior', function (): void {
        // Arrange & Act
        $instance1 = Arbiter::getFacadeRoot();
        $instance2 = Arbiter::getFacadeRoot();

        // Assert
        expect($instance1)->toBe($instance2);
    });
});

describe('Policy Registration - register()', function (): void {
    test('registers policy successfully', function (): void {
        // Arrange
        $policy = Policy::create('test-policy')
            ->addRule(Rule::allow('/users/*')->capabilities(Capability::Read));

        // Act
        Arbiter::register($policy);

        // Assert
        expect(Arbiter::has('test-policy'))->toBeTrue();
        expect(Arbiter::get('test-policy'))->toBe($policy);
    });

    test('registers multiple policies', function (): void {
        // Arrange
        $policy1 = Policy::create('policy-1')
            ->addRule(Rule::allow('/users/*')->capabilities(Capability::Read));
        $policy2 = Policy::create('policy-2')
            ->addRule(Rule::allow('/posts/*')->capabilities(Capability::Create));

        // Act
        Arbiter::register($policy1);
        Arbiter::register($policy2);

        // Assert
        expect(Arbiter::has('policy-1'))->toBeTrue();
        expect(Arbiter::has('policy-2'))->toBeTrue();
    });

    test('overwrites existing policy with same name', function (): void {
        // Arrange
        $policy1 = Policy::create('duplicate')
            ->addRule(Rule::allow('/old/*')->capabilities(Capability::Read));
        $policy2 = Policy::create('duplicate')
            ->addRule(Rule::allow('/new/*')->capabilities(Capability::Update));

        // Act
        Arbiter::register($policy1);
        Arbiter::register($policy2);

        // Assert
        $retrieved = Arbiter::get('duplicate');
        expect($retrieved)->toBe($policy2);
        expect($retrieved)->not->toBe($policy1);
    });
});

describe('Policy Retrieval - has()', function (): void {
    test('returns true when policy exists', function (): void {
        // Arrange
        $policy = Policy::create('existing-policy')
            ->addRule(Rule::allow('/resources/*')->capabilities(Capability::Read));
        Arbiter::register($policy);

        // Act
        $result = Arbiter::has('existing-policy');

        // Assert
        expect($result)->toBeTrue();
    });

    test('returns false when policy does not exist', function (): void {
        // Arrange
        // No policies registered

        // Act
        $result = Arbiter::has('non-existent-policy');

        // Assert
        expect($result)->toBeFalse();
    });

    test('case sensitivity in policy names', function (): void {
        // Arrange
        $policy = Policy::create('CaseSensitive')
            ->addRule(Rule::allow('/test/*')->capabilities(Capability::Read));
        Arbiter::register($policy);

        // Act & Assert
        expect(Arbiter::has('CaseSensitive'))->toBeTrue();
        expect(Arbiter::has('casesensitive'))->toBeFalse();
        expect(Arbiter::has('CASESENSITIVE'))->toBeFalse();
    });
});

describe('Policy Retrieval - get()', function (): void {
    test('retrieves registered policy by name', function (): void {
        // Arrange
        $policy = Policy::create('retrieve-test')
            ->addRule(Rule::allow('/data/*')->capabilities(Capability::Read));
        Arbiter::register($policy);

        // Act
        $retrieved = Arbiter::get('retrieve-test');

        // Assert
        expect($retrieved)->toBe($policy);
        expect($retrieved->getName())->toBe('retrieve-test');
    });

    test('throws exception when policy not found', function (): void {
        // Arrange
        // No policies registered

        // Act & Assert
        expect(fn () => Arbiter::get('missing-policy'))
            ->toThrow(PolicyNotFoundException::class);
    });

    test('retrieves correct policy among multiple', function (): void {
        // Arrange
        $policy1 = Policy::create('first')
            ->addRule(Rule::allow('/first/*')->capabilities(Capability::Read));
        $policy2 = Policy::create('second')
            ->addRule(Rule::allow('/second/*')->capabilities(Capability::Update));
        $policy3 = Policy::create('third')
            ->addRule(Rule::allow('/third/*')->capabilities(Capability::Delete));

        Arbiter::register($policy1);
        Arbiter::register($policy2);
        Arbiter::register($policy3);

        // Act
        $retrieved = Arbiter::get('second');

        // Assert
        expect($retrieved)->toBe($policy2);
        expect($retrieved->getName())->toBe('second');
    });
});

describe('Policy Retrieval - all()', function (): void {
    test('returns empty array when no policies registered', function (): void {
        // Arrange
        // No policies registered

        // Act
        $all = Arbiter::all();

        // Assert
        expect($all)->toBeArray();
        expect($all)->toHaveCount(0);
    });

    test('returns all registered policies', function (): void {
        // Arrange
        $policy1 = Policy::create('alpha')
            ->addRule(Rule::allow('/alpha/*')->capabilities(Capability::Read));
        $policy2 = Policy::create('beta')
            ->addRule(Rule::allow('/beta/*')->capabilities(Capability::Update));
        $policy3 = Policy::create('gamma')
            ->addRule(Rule::allow('/gamma/*')->capabilities(Capability::Delete));

        Arbiter::register($policy1);
        Arbiter::register($policy2);
        Arbiter::register($policy3);

        // Act
        $all = Arbiter::all();

        // Assert
        expect($all)->toBeArray();
        expect($all)->toHaveCount(3);
        expect($all)->toMatchArray([
            'alpha' => $policy1,
            'beta' => $policy2,
            'gamma' => $policy3,
        ]);
    });

    test('all() returns associative array with policy names as keys', function (): void {
        // Arrange
        $policy = Policy::create('keyed-policy')
            ->addRule(Rule::allow('/test/*')->capabilities(Capability::Read));
        Arbiter::register($policy);

        // Act
        $all = Arbiter::all();

        // Assert
        expect($all)->toHaveKey('keyed-policy');
        expect($all['keyed-policy'])->toBe($policy);
    });
});

describe('Policy Evaluation - for()', function (): void {
    test('creates PolicyEvaluationConductor with policy name string', function (): void {
        // Arrange
        $policy = Policy::create('string-policy')
            ->addRule(Rule::allow('/users/*')->capabilities(Capability::Read));
        Arbiter::register($policy);

        // Act
        $conductor = Arbiter::for('string-policy');

        // Assert
        expect($conductor)->toBeInstanceOf(PolicyEvaluationConductor::class);
    });

    test('creates PolicyEvaluationConductor with Policy instance', function (): void {
        // Arrange
        $policy = Policy::create('instance-policy')
            ->addRule(Rule::allow('/posts/*')->capabilities(Capability::Update));
        Arbiter::register($policy);

        // Act
        $conductor = Arbiter::for($policy);

        // Assert
        expect($conductor)->toBeInstanceOf(PolicyEvaluationConductor::class);
    });

    test('creates PolicyEvaluationConductor with array of policy names', function (): void {
        // Arrange
        $policy1 = Policy::create('multi-1')
            ->addRule(Rule::allow('/resource1/*')->capabilities(Capability::Read));
        $policy2 = Policy::create('multi-2')
            ->addRule(Rule::allow('/resource2/*')->capabilities(Capability::Update));
        Arbiter::register($policy1);
        Arbiter::register($policy2);

        // Act
        $conductor = Arbiter::for(['multi-1', 'multi-2']);

        // Assert
        expect($conductor)->toBeInstanceOf(PolicyEvaluationConductor::class);
    });

    test('creates PolicyEvaluationConductor with array of Policy instances', function (): void {
        // Arrange
        $policy1 = Policy::create('instance-1')
            ->addRule(Rule::allow('/api1/*')->capabilities(Capability::Read));
        $policy2 = Policy::create('instance-2')
            ->addRule(Rule::allow('/api2/*')->capabilities(Capability::Update));

        // Act
        $conductor = Arbiter::for([$policy1, $policy2]);

        // Assert
        expect($conductor)->toBeInstanceOf(PolicyEvaluationConductor::class);
    });

    test('creates PolicyEvaluationConductor with mixed array of names and instances', function (): void {
        // Arrange
        $policy1 = Policy::create('mixed-1')
            ->addRule(Rule::allow('/mixed1/*')->capabilities(Capability::Read));
        $policy2 = Policy::create('mixed-2')
            ->addRule(Rule::allow('/mixed2/*')->capabilities(Capability::Update));
        Arbiter::register($policy1);

        // Act
        $conductor = Arbiter::for(['mixed-1', $policy2]);

        // Assert
        expect($conductor)->toBeInstanceOf(PolicyEvaluationConductor::class);
    });

    test('conductor can evaluate permissions', function (): void {
        // Arrange
        $policy = Policy::create('eval-policy')
            ->addRule(Rule::allow('/allowed/*')->capabilities(Capability::Read))
            ->addRule(Rule::deny('/denied/*'));
        Arbiter::register($policy);

        // Act
        $allowedResult = Arbiter::for('eval-policy')->can('/allowed/resource', Capability::Read);
        $deniedResult = Arbiter::for('eval-policy')->can('/denied/resource', Capability::Read);

        // Assert
        expect($allowedResult->allowed())->toBeTrue();
        expect($deniedResult->allowed())->toBeFalse();
    });
});

describe('Path Evaluation - path()', function (): void {
    test('creates PathEvaluationConductor', function (): void {
        // Arrange
        $path = '/users/123';

        // Act
        $conductor = Arbiter::path($path);

        // Assert
        expect($conductor)->toBeInstanceOf(PathEvaluationConductor::class);
    });

    test('path conductor evaluates against registered policy', function (): void {
        // Arrange
        $policy = Policy::create('path-policy')
            ->addRule(Rule::allow('/users/*')->capabilities(Capability::Read));
        Arbiter::register($policy);

        // Act
        $result = Arbiter::path('/users/123')->against('path-policy')->allows(Capability::Read);

        // Assert
        expect($result)->toBeTrue();
    });

    test('path conductor works with wildcard paths', function (): void {
        // Arrange
        $policy = Policy::create('wildcard-policy')
            ->addRule(Rule::allow('/api/**')->capabilities(Capability::Read, Capability::Update));
        Arbiter::register($policy);

        // Act
        $result = Arbiter::path('/api/v1/users/123/posts/456')->against('wildcard-policy')->allows(Capability::Read);

        // Assert
        expect($result)->toBeTrue();
    });

    test('path conductor respects deny rules', function (): void {
        // Arrange
        $policy = Policy::create('deny-policy')
            ->addRule(Rule::allow('/resources/**')->capabilities(Capability::Read))
            ->addRule(Rule::deny('/resources/secret/**'));
        Arbiter::register($policy);

        // Act
        $allowedResult = Arbiter::path('/resources/public/doc')->against('deny-policy')->allows(Capability::Read);
        $deniedResult = Arbiter::path('/resources/secret/data')->against('deny-policy')->allows(Capability::Read);

        // Assert
        expect($allowedResult)->toBeTrue();
        expect($deniedResult)->toBeFalse();
    });

    test('path conductor handles root paths', function (): void {
        // Arrange
        $policy = Policy::create('root-policy')
            ->addRule(Rule::allow('/')->capabilities(Capability::Read));
        Arbiter::register($policy);

        // Act
        $result = Arbiter::path('/')->against('root-policy')->allows(Capability::Read);

        // Assert
        expect($result)->toBeTrue();
    });
});

describe('Repository Management - repository()', function (): void {
    test('sets repository and returns manager instance', function (): void {
        // Arrange
        $repository = new ArrayRepository([]);

        // Act
        $result = Arbiter::repository($repository);

        // Assert
        expect($result)->toBeInstanceOf(ArbiterManager::class);
    });

    test('repository loads policies into registry', function (): void {
        // Arrange
        $policy = Policy::create('repo-policy')
            ->addRule(Rule::allow('/data/*')->capabilities(Capability::Read));
        $repository = new ArrayRepository([$policy]);

        // Act
        Arbiter::repository($repository);

        // Assert
        expect(Arbiter::has('repo-policy'))->toBeTrue();
    });

    test('repository can be changed multiple times', function (): void {
        // Arrange
        $policy1 = Policy::create('policy-from-repo1')
            ->addRule(Rule::allow('/repo1/*')->capabilities(Capability::Read));
        $repo1 = new ArrayRepository([$policy1]);

        $policy2 = Policy::create('policy-from-repo2')
            ->addRule(Rule::allow('/repo2/*')->capabilities(Capability::Update));
        $repo2 = new ArrayRepository([$policy2]);

        // Act
        Arbiter::repository($repo1);
        expect(Arbiter::has('policy-from-repo1'))->toBeTrue();

        Arbiter::repository($repo2);

        // Assert
        expect(Arbiter::has('policy-from-repo2'))->toBeTrue();
    });

    test('repository integrates with custom PolicyRepositoryInterface implementation', function (): void {
        // Arrange
        $customRepo = new class() implements PolicyRepositoryInterface
        {
            private array $policies;

            public function __construct()
            {
                $this->policies = [
                    'custom-repo-policy' => Policy::create('custom-repo-policy')
                        ->addRule(Rule::allow('/custom/*')->capabilities(Capability::Read)),
                ];
            }

            public function get(string $name): Policy
            {
                throw_unless($this->has($name), PolicyNotFoundException::class, $name);

                return $this->policies[$name];
            }

            public function has(string $name): bool
            {
                return isset($this->policies[$name]);
            }

            public function all(): array
            {
                return $this->policies;
            }

            public function getMany(array $names): array
            {
                $result = [];

                foreach ($names as $name) {
                    $result[$name] = $this->get($name);
                }

                return $result;
            }
        };

        // Act
        Arbiter::repository($customRepo);

        // Assert
        expect(Arbiter::has('custom-repo-policy'))->toBeTrue();
    });
});

describe('Integration Scenarios', function (): void {
    test('facade supports full policy lifecycle', function (): void {
        // Arrange
        $policy = Policy::create('lifecycle-policy')
            ->addRule(Rule::allow('/users/*')->capabilities(Capability::Read, Capability::Update))
            ->addRule(Rule::deny('/users/admin'));

        // Act - Register
        Arbiter::register($policy);

        // Assert - Exists
        expect(Arbiter::has('lifecycle-policy'))->toBeTrue();

        // Act - Retrieve
        $retrieved = Arbiter::get('lifecycle-policy');
        expect($retrieved)->toBe($policy);

        // Act - Evaluate
        $allowedResult = Arbiter::for('lifecycle-policy')->can('/users/john', Capability::Read);
        $deniedResult = Arbiter::for('lifecycle-policy')->can('/users/admin', Capability::Read);

        // Assert - Evaluation
        expect($allowedResult->allowed())->toBeTrue();
        expect($deniedResult->allowed())->toBeFalse();
    });

    test('facade supports complex multi-policy scenarios', function (): void {
        // Arrange
        $userPolicy = Policy::create('user-policy')
            ->addRule(Rule::allow('/users/**')->capabilities(Capability::Read));

        $adminPolicy = Policy::create('admin-policy')
            ->addRule(Rule::allow('/users/**')->capabilities(Capability::Read, Capability::Update, Capability::Delete))
            ->addRule(Rule::allow('/admin/**')->capabilities(Capability::Read, Capability::Update));

        // Act
        Arbiter::register($userPolicy);
        Arbiter::register($adminPolicy);

        // Assert - Both policies registered
        $all = Arbiter::all();
        expect($all)->toHaveCount(2);
        expect($all)->toHaveKey('user-policy');
        expect($all)->toHaveKey('admin-policy');

        // Assert - User policy evaluation
        $userRead = Arbiter::for('user-policy')->can('/users/123', Capability::Read);
        $userUpdate = Arbiter::for('user-policy')->can('/users/123', Capability::Update);
        expect($userRead->allowed())->toBeTrue();
        expect($userUpdate->allowed())->toBeFalse();

        // Assert - Admin policy evaluation
        $adminRead = Arbiter::for('admin-policy')->can('/users/123', Capability::Read);
        $adminUpdate = Arbiter::for('admin-policy')->can('/users/123', Capability::Update);
        $adminDelete = Arbiter::for('admin-policy')->can('/users/123', Capability::Delete);
        expect($adminRead->allowed())->toBeTrue();
        expect($adminUpdate->allowed())->toBeTrue();
        expect($adminDelete->allowed())->toBeTrue();
    });

    test('facade maintains state across multiple operations', function (): void {
        // Arrange
        $policy1 = Policy::create('state-1')
            ->addRule(Rule::allow('/resource1/*')->capabilities(Capability::Read));
        $policy2 = Policy::create('state-2')
            ->addRule(Rule::allow('/resource2/*')->capabilities(Capability::Update));

        // Act - First operation
        Arbiter::register($policy1);
        expect(Arbiter::all())->toHaveCount(1);

        // Act - Second operation
        Arbiter::register($policy2);

        // Assert - State persisted
        expect(Arbiter::all())->toHaveCount(2);
        expect(Arbiter::has('state-1'))->toBeTrue();
        expect(Arbiter::has('state-2'))->toBeTrue();
    });

    test('facade supports chained method calls', function (): void {
        // Arrange
        $policy = Policy::create('chained-policy')
            ->addRule(Rule::allow('/api/**')->capabilities(Capability::Read, Capability::Update));
        $repository = new ArrayRepository([$policy]);

        // Act - Chain repository setup and evaluation
        $result = Arbiter::repository($repository)
            ->for('chained-policy')
            ->can('/api/v1/users', Capability::Read);

        // Assert
        expect($result->allowed())->toBeTrue();
    });

    test('facade supports path-first evaluation workflow', function (): void {
        // Arrange
        $policy = Policy::create('path-first-policy')
            ->addRule(Rule::allow('/documents/**')->capabilities(Capability::Read))
            ->addRule(Rule::deny('/documents/private/**'));
        Arbiter::register($policy);

        // Act
        $publicDoc = Arbiter::path('/documents/public/readme.txt')
            ->against('path-first-policy')
            ->allows(Capability::Read);

        $privateDoc = Arbiter::path('/documents/private/secret.txt')
            ->against('path-first-policy')
            ->allows(Capability::Read);

        // Assert
        expect($publicDoc)->toBeTrue();
        expect($privateDoc)->toBeFalse();
    });

    test('facade supports policy-first evaluation workflow', function (): void {
        // Arrange
        $policy = Policy::create('policy-first-policy')
            ->addRule(Rule::allow('/images/**')->capabilities(Capability::Read))
            ->addRule(Rule::allow('/images/uploads/**')->capabilities(Capability::Update));
        Arbiter::register($policy);

        // Act
        $readOnly = Arbiter::for('policy-first-policy')
            ->can('/images/gallery/photo.jpg', Capability::Read);

        $readUpdate = Arbiter::for('policy-first-policy')
            ->can('/images/uploads/new.jpg', Capability::Update);

        $unauthorized = Arbiter::for('policy-first-policy')
            ->can('/images/gallery/photo.jpg', Capability::Update);

        // Assert
        expect($readOnly->allowed())->toBeTrue();
        expect($readUpdate->allowed())->toBeTrue();
        expect($unauthorized->allowed())->toBeFalse();
    });

    test('facade supports evaluating with Admin capability', function (): void {
        // Arrange
        $policy = Policy::create('admin-capability-policy')
            ->addRule(Rule::allow('/system/**')->capabilities(Capability::Admin));
        Arbiter::register($policy);

        // Act
        $adminAccess = Arbiter::for('admin-capability-policy')
            ->can('/system/settings', Capability::Admin);

        $readAccess = Arbiter::for('admin-capability-policy')
            ->can('/system/settings', Capability::Read);

        // Assert
        expect($adminAccess->allowed())->toBeTrue();
        // Admin capability implies all others
        expect($readAccess->allowed())->toBeTrue();
    });

    test('facade supports evaluating with List capability', function (): void {
        // Arrange
        $policy = Policy::create('list-capability-policy')
            ->addRule(Rule::allow('/users')->capabilities(Capability::List))
            ->addRule(Rule::allow('/users/*')->capabilities(Capability::Read));
        Arbiter::register($policy);

        // Act
        $listAccess = Arbiter::for('list-capability-policy')
            ->can('/users', Capability::List);

        $readAccess = Arbiter::for('list-capability-policy')
            ->can('/users/123', Capability::Read);

        // Assert
        expect($listAccess->allowed())->toBeTrue();
        expect($readAccess->allowed())->toBeTrue();
    });

    test('facade supports evaluating with Create capability', function (): void {
        // Arrange
        $policy = Policy::create('create-capability-policy')
            ->addRule(Rule::allow('/posts')->capabilities(Capability::Create))
            ->addRule(Rule::allow('/posts/*')->capabilities(Capability::Read));
        Arbiter::register($policy);

        // Act
        $createAccess = Arbiter::for('create-capability-policy')
            ->can('/posts', Capability::Create);

        $readAccess = Arbiter::for('create-capability-policy')
            ->can('/posts/123', Capability::Read);

        $updateAccess = Arbiter::for('create-capability-policy')
            ->can('/posts/123', Capability::Update);

        // Assert
        expect($createAccess->allowed())->toBeTrue();
        expect($readAccess->allowed())->toBeTrue();
        expect($updateAccess->allowed())->toBeFalse();
    });
});
