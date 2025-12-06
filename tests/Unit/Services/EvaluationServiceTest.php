<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\Capability;
use Cline\Arbiter\Effect;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Rule;
use Cline\Arbiter\Services\EvaluationService;
use Cline\Arbiter\Services\SpecificityCalculator;

beforeEach(function (): void {
    $this->evaluator = new EvaluationService(
        new SpecificityCalculator(),
    );
});

describe('evaluate() method', function (): void {
    test('returns implicit deny when no rules match', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read));
        $policies = [$policy];

        // Act
        $result = $this->evaluator->evaluate($policies, Capability::Read, '/api/posts');

        // Assert
        expect($result->isDenied())->toBeTrue()
            ->and($result->isExplicitDeny())->toBeFalse()
            ->and($result->getMatchedRule())->toBeNull();
    });

    test('returns explicit deny when deny rule matches', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/**')->capabilities(Capability::Read))
            ->addRule(Rule::deny('/api/admin'));
        $policies = [$policy];

        // Act
        $result = $this->evaluator->evaluate($policies, Capability::Read, '/api/admin');

        // Assert
        expect($result->isDenied())->toBeTrue()
            ->and($result->isExplicitDeny())->toBeTrue()
            ->and($result->getMatchedRule())->not->toBeNull()
            ->and($result->getMatchedRule()->getEffect())->toBe(Effect::Deny);
    });

    test('returns allowed when allow rule matches', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read));
        $policies = [$policy];

        // Act
        $result = $this->evaluator->evaluate($policies, Capability::Read, '/api/users');

        // Assert
        expect($result->isAllowed())->toBeTrue()
            ->and($result->getMatchedRule())->not->toBeNull()
            ->and($result->getMatchedRule()->getEffect())->toBe(Effect::Allow);
    });

    test('deny rule takes precedence over allow rule regardless of specificity', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users/123')->capabilities(Capability::Read)) // More specific
            ->addRule(Rule::deny('/api/**')); // Less specific
        $policies = [$policy];

        // Act
        $result = $this->evaluator->evaluate($policies, Capability::Read, '/api/users/123');

        // Assert
        expect($result->isDenied())->toBeTrue()
            ->and($result->isExplicitDeny())->toBeTrue()
            ->and($result->getMatchedRule()->getEffect())->toBe(Effect::Deny);
    });

    test('skips rules that do not match path', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read))
            ->addRule(Rule::allow('/api/posts')->capabilities(Capability::Read));
        $policies = [$policy];

        // Act
        $result = $this->evaluator->evaluate($policies, Capability::Read, '/api/posts');

        // Assert
        expect($result->isAllowed())->toBeTrue()
            ->and($result->getMatchedRule()->getPath())->toBe('/api/posts');
    });

    test('skips rules with unsatisfied conditions', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(
                Rule::allow('/api/users')
                    ->capabilities(Capability::Read)
                    ->when('role', 'admin'),
            )
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read));
        $policies = [$policy];

        // Act
        $result = $this->evaluator->evaluate(
            $policies,
            Capability::Read,
            '/api/users',
            ['role' => 'user'],
        );

        // Assert
        expect($result->isAllowed())->toBeTrue()
            ->and($result->getMatchedRule()->getConditions())->toBe([]);
    });

    test('includes deny rules without capability check', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/**')->capabilities(Capability::Read))
            ->addRule(Rule::deny('/api/admin')); // No capabilities needed for deny
        $policies = [$policy];

        // Act
        $result = $this->evaluator->evaluate($policies, Capability::Update, '/api/admin');

        // Assert
        expect($result->isDenied())->toBeTrue()
            ->and($result->isExplicitDeny())->toBeTrue();
    });

    test('skips allow rules without matching capability', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read))
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Update));
        $policies = [$policy];

        // Act
        $result = $this->evaluator->evaluate($policies, Capability::Update, '/api/users');

        // Assert
        expect($result->isAllowed())->toBeTrue()
            ->and($result->getMatchedRule()->hasCapability(Capability::Update))->toBeTrue();
    });

    test('sorts matching rules by specificity', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/**')->capabilities(Capability::Read))
            ->addRule(Rule::allow('/api/users/*')->capabilities(Capability::Update))
            ->addRule(Rule::allow('/api/users/123')->capabilities(Capability::Delete));
        $policies = [$policy];

        // Act
        $result = $this->evaluator->evaluate($policies, Capability::Delete, '/api/users/123');

        // Assert
        expect($result->isAllowed())->toBeTrue()
            ->and($result->getMatchedRule()->getPath())->toBe('/api/users/123');
    });

    test('evaluates rules across multiple policies', function (): void {
        // Arrange
        $policy1 = Policy::create('base')
            ->addRule(Rule::allow('/api/**')->capabilities(Capability::Read));
        $policy2 = Policy::create('service')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Update));
        $policies = [$policy1, $policy2];

        // Act
        $result = $this->evaluator->evaluate($policies, Capability::Update, '/api/users');

        // Assert
        expect($result->isAllowed())->toBeTrue()
            ->and($result->getMatchedPolicy()->getName())->toBe('service');
    });

    test('handles variable substitution in path matching', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/users/${user_id}/data')->capabilities(Capability::Read));
        $policies = [$policy];

        // Act
        $result = $this->evaluator->evaluate(
            $policies,
            Capability::Read,
            '/users/123/data',
            ['user_id' => '123'],
        );

        // Assert
        expect($result->isAllowed())->toBeTrue();
    });

    test('returns first allow rule when multiple rules match with same specificity', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read)->description('First'))
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read)->description('Second'));
        $policies = [$policy];

        // Act
        $result = $this->evaluator->evaluate($policies, Capability::Read, '/api/users');

        // Assert
        expect($result->isAllowed())->toBeTrue()
            ->and($result->getMatchedRule()->getDescription())->toBe('First');
    });
});

describe('listAccessiblePaths() method - Happy Path', function (): void {
    test('returns paths from allow rules with matching capability', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read))
            ->addRule(Rule::allow('/api/posts')->capabilities(Capability::Read));
        $policies = [$policy];

        // Act
        $paths = $this->evaluator->listAccessiblePaths($policies, Capability::Read);

        // Assert
        expect($paths)->toBe(['/api/users', '/api/posts']);
    });

    test('returns unique paths when duplicates exist', function (): void {
        // Arrange
        $policy1 = Policy::create('base')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read));
        $policy2 = Policy::create('service')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read));
        $policies = [$policy1, $policy2];

        // Act
        $paths = $this->evaluator->listAccessiblePaths($policies, Capability::Read);

        // Assert
        expect($paths)->toBe(['/api/users']);
    });

    test('returns paths across multiple policies', function (): void {
        // Arrange
        $policy1 = Policy::create('base')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read));
        $policy2 = Policy::create('service')
            ->addRule(Rule::allow('/api/posts')->capabilities(Capability::Read));
        $policies = [$policy1, $policy2];

        // Act
        $paths = $this->evaluator->listAccessiblePaths($policies, Capability::Read);

        // Assert
        expect($paths)->toBe(['/api/users', '/api/posts']);
    });

    test('returns wildcard paths', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/*')->capabilities(Capability::Read))
            ->addRule(Rule::allow('/data/**')->capabilities(Capability::Read));
        $policies = [$policy];

        // Act
        $paths = $this->evaluator->listAccessiblePaths($policies, Capability::Read);

        // Assert
        expect($paths)->toBe(['/api/*', '/data/**']);
    });

    test('returns empty array when no policies provided', function (): void {
        // Arrange
        $policies = [];

        // Act
        $paths = $this->evaluator->listAccessiblePaths($policies, Capability::Read);

        // Assert
        expect($paths)->toBe([]);
    });

    test('returns empty array when policies have no rules', function (): void {
        // Arrange
        $policy = Policy::create('test');
        $policies = [$policy];

        // Act
        $paths = $this->evaluator->listAccessiblePaths($policies, Capability::Read);

        // Assert
        expect($paths)->toBe([]);
    });
});

describe('listAccessiblePaths() method - Edge Cases', function (): void {
    test('excludes deny rules from accessible paths', function (): void {
        // Arrange - This tests line 150
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read))
            ->addRule(Rule::deny('/api/admin'));
        $policies = [$policy];

        // Act
        $paths = $this->evaluator->listAccessiblePaths($policies, Capability::Read);

        // Assert
        expect($paths)->toBe(['/api/users'])
            ->and($paths)->not->toContain('/api/admin');
    });

    test('excludes rules without matching capability', function (): void {
        // Arrange - This tests line 154
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read))
            ->addRule(Rule::allow('/api/posts')->capabilities(Capability::Update));
        $policies = [$policy];

        // Act
        $paths = $this->evaluator->listAccessiblePaths($policies, Capability::Read);

        // Assert
        expect($paths)->toBe(['/api/users'])
            ->and($paths)->not->toContain('/api/posts');
    });

    test('handles rules with multiple capabilities', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read, Capability::Update))
            ->addRule(Rule::allow('/api/posts')->capabilities(Capability::Update));
        $policies = [$policy];

        // Act
        $pathsRead = $this->evaluator->listAccessiblePaths($policies, Capability::Read);
        $pathsUpdate = $this->evaluator->listAccessiblePaths($policies, Capability::Update);

        // Assert
        expect($pathsRead)->toBe(['/api/users'])
            ->and($pathsUpdate)->toBe(['/api/users', '/api/posts']);
    });

    test('handles admin capability returning all paths', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/admin')->capabilities(Capability::Admin));
        $policies = [$policy];

        // Act
        $paths = $this->evaluator->listAccessiblePaths($policies, Capability::Admin);

        // Assert
        expect($paths)->toBe(['/api/admin']);
    });
});

describe('getCapabilities() method - Happy Path', function (): void {
    test('returns capabilities from allow rules matching path', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read, Capability::Update));
        $policies = [$policy];

        // Act
        $capabilities = $this->evaluator->getCapabilities($policies, '/api/users');

        // Assert
        expect($capabilities)->toBe([Capability::Read, Capability::Update]);
    });

    test('returns unique capabilities when duplicates exist', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read))
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read, Capability::Update));
        $policies = [$policy];

        // Act
        $capabilities = $this->evaluator->getCapabilities($policies, '/api/users');

        // Assert
        expect($capabilities)->toBe([Capability::Read, Capability::Update]);
    });

    test('returns capabilities from wildcard paths', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/*')->capabilities(Capability::Read));
        $policies = [$policy];

        // Act
        $capabilities = $this->evaluator->getCapabilities($policies, '/api/users');

        // Assert
        expect($capabilities)->toBe([Capability::Read]);
    });

    test('returns capabilities from glob wildcard paths', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/**')->capabilities(Capability::Read));
        $policies = [$policy];

        // Act
        $capabilities = $this->evaluator->getCapabilities($policies, '/api/users/123/profile');

        // Assert
        expect($capabilities)->toBe([Capability::Read]);
    });

    test('returns empty array when no rules match path', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read));
        $policies = [$policy];

        // Act
        $capabilities = $this->evaluator->getCapabilities($policies, '/api/posts');

        // Assert
        expect($capabilities)->toBe([]);
    });

    test('returns empty array when no policies provided', function (): void {
        // Arrange
        $policies = [];

        // Act
        $capabilities = $this->evaluator->getCapabilities($policies, '/api/users');

        // Assert
        expect($capabilities)->toBe([]);
    });

    test('returns capabilities across multiple policies', function (): void {
        // Arrange
        $policy1 = Policy::create('base')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read));
        $policy2 = Policy::create('service')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Update));
        $policies = [$policy1, $policy2];

        // Act
        $capabilities = $this->evaluator->getCapabilities($policies, '/api/users');

        // Assert
        expect($capabilities)->toBe([Capability::Read, Capability::Update]);
    });
});

describe('getCapabilities() method - Edge Cases', function (): void {
    test('excludes capabilities from rules with non-matching paths', function (): void {
        // Arrange - This tests line 183
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read))
            ->addRule(Rule::allow('/api/posts')->capabilities(Capability::Update));
        $policies = [$policy];

        // Act
        $capabilities = $this->evaluator->getCapabilities($policies, '/api/users');

        // Assert
        expect($capabilities)->toBe([Capability::Read])
            ->and($capabilities)->not->toContain(Capability::Update);
    });

    test('excludes capabilities from rules with unsatisfied conditions', function (): void {
        // Arrange - This tests line 187
        $policy = Policy::create('test')
            ->addRule(
                Rule::allow('/api/users')
                    ->capabilities(Capability::Read)
                    ->when('role', 'admin'),
            )
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Update));
        $policies = [$policy];

        // Act
        $capabilities = $this->evaluator->getCapabilities(
            $policies,
            '/api/users',
            ['role' => 'user'],
        );

        // Assert
        expect($capabilities)->toBe([Capability::Update])
            ->and($capabilities)->not->toContain(Capability::Read);
    });

    test('excludes capabilities from deny rules', function (): void {
        // Arrange - This tests line 191
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read))
            ->addRule(Rule::deny('/api/users'));
        $policies = [$policy];

        // Act
        $capabilities = $this->evaluator->getCapabilities($policies, '/api/users');

        // Assert
        expect($capabilities)->toBe([Capability::Read]);
    });

    test('handles variable substitution in path matching', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/users/${user_id}/data')->capabilities(Capability::Read));
        $policies = [$policy];

        // Act
        $capabilitiesMatch = $this->evaluator->getCapabilities(
            $policies,
            '/users/123/data',
            ['user_id' => '123'],
        );
        $capabilitiesNoMatch = $this->evaluator->getCapabilities(
            $policies,
            '/users/456/data',
            ['user_id' => '123'],
        );

        // Assert
        expect($capabilitiesMatch)->toBe([Capability::Read])
            ->and($capabilitiesNoMatch)->toBe([]);
    });

    test('handles complex condition expressions', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(
                Rule::allow('/api/users')
                    ->capabilities(Capability::Read)
                    ->when('role', ['admin', 'manager']),
            );
        $policies = [$policy];

        // Act
        $capabilitiesAdmin = $this->evaluator->getCapabilities(
            $policies,
            '/api/users',
            ['role' => 'admin'],
        );
        $capabilitiesManager = $this->evaluator->getCapabilities(
            $policies,
            '/api/users',
            ['role' => 'manager'],
        );
        $capabilitiesUser = $this->evaluator->getCapabilities(
            $policies,
            '/api/users',
            ['role' => 'user'],
        );

        // Assert
        expect($capabilitiesAdmin)->toBe([Capability::Read])
            ->and($capabilitiesManager)->toBe([Capability::Read])
            ->and($capabilitiesUser)->toBe([]);
    });

    test('handles rules with conditions satisfied', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(
                Rule::allow('/api/users')
                    ->capabilities(Capability::Read)
                    ->when('environment', 'production'),
            );
        $policies = [$policy];

        // Act
        $capabilitiesProduction = $this->evaluator->getCapabilities(
            $policies,
            '/api/users',
            ['environment' => 'production'],
        );
        $capabilitiesStaging = $this->evaluator->getCapabilities(
            $policies,
            '/api/users',
            ['environment' => 'staging'],
        );

        // Assert
        expect($capabilitiesProduction)->toBe([Capability::Read])
            ->and($capabilitiesStaging)->toBe([]);
    });

    test('handles empty context array', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read));
        $policies = [$policy];

        // Act
        $capabilities = $this->evaluator->getCapabilities($policies, '/api/users', []);

        // Assert
        expect($capabilities)->toBe([Capability::Read]);
    });
});

describe('getCapabilities() method - Regression Cases', function (): void {
    test('handles policies with no rules', function (): void {
        // Arrange
        $policy = Policy::create('test');
        $policies = [$policy];

        // Act
        $capabilities = $this->evaluator->getCapabilities($policies, '/api/users');

        // Assert
        expect($capabilities)->toBe([]);
    });

    test('handles multiple wildcard patterns', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/**')->capabilities(Capability::Read))
            ->addRule(Rule::allow('/api/users/**')->capabilities(Capability::Update))
            ->addRule(Rule::allow('/api/users/*')->capabilities(Capability::Create));
        $policies = [$policy];

        // Act
        $capabilities = $this->evaluator->getCapabilities($policies, '/api/users/123');

        // Assert
        expect($capabilities)->toBe([Capability::Read, Capability::Update, Capability::Create]);
    });

    test('maintains capability uniqueness across multiple matching rules', function (): void {
        // Arrange
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/api/*')->capabilities(Capability::Read))
            ->addRule(Rule::allow('/api/**')->capabilities(Capability::Read))
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read));
        $policies = [$policy];

        // Act
        $capabilities = $this->evaluator->getCapabilities($policies, '/api/users');

        // Assert
        expect($capabilities)->toBe([Capability::Read])
            ->and($capabilities)->toHaveCount(1);
    });
});
