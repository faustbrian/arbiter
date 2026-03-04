<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\Capability;
use Cline\Arbiter\Effect;
use Cline\Arbiter\Rule;

describe('Rule factory methods', function (): void {
    test('allow() creates rule with Allow effect', function (): void {
        $rule = Rule::allow('/api/users');

        expect($rule->getPath())->toBe('/api/users')
            ->and($rule->getEffect())->toBe(Effect::Allow);
    });

    test('deny() creates rule with Deny effect', function (): void {
        $rule = Rule::deny('/api/admin');

        expect($rule->getPath())->toBe('/api/admin')
            ->and($rule->getEffect())->toBe(Effect::Deny);
    });
});

describe('Rule capabilities() fluent API', function (): void {
    test('capabilities() sets single capability', function (): void {
        $rule = Rule::allow('/api/users')
            ->capabilities(Capability::Read);

        expect($rule->getCapabilities())->toBe([Capability::Read]);
    });

    test('capabilities() sets multiple capabilities', function (): void {
        $rule = Rule::allow('/api/users')
            ->capabilities(Capability::Read, Capability::List, Capability::Update);

        expect($rule->getCapabilities())->toBe([
            Capability::Read,
            Capability::List,
            Capability::Update,
        ]);
    });

    test('capabilities() returns new instance (immutability)', function (): void {
        $rule1 = Rule::allow('/api/users');
        $rule2 = $rule1->capabilities(Capability::Read);

        expect($rule1)->not->toBe($rule2)
            ->and($rule1->getCapabilities())->toBe([])
            ->and($rule2->getCapabilities())->toBe([Capability::Read]);
    });
});

describe('Rule when() conditions', function (): void {
    test('when() adds condition with equals value', function (): void {
        $rule = Rule::allow('/api/users')
            ->when('role', 'admin');

        expect($rule->getConditions())->toBe(['role' => 'admin']);
    });

    test('when() adds condition with integer value', function (): void {
        $rule = Rule::allow('/api/users')
            ->when('user_id', 123);

        expect($rule->getConditions())->toBe(['user_id' => 123]);
    });

    test('when() adds condition with array value', function (): void {
        $rule = Rule::allow('/api/users')
            ->when('role', ['admin', 'moderator']);

        expect($rule->getConditions())->toBe(['role' => ['admin', 'moderator']]);
    });

    test('when() adds condition with callable', function (): void {
        $callback = fn ($value): bool => $value > 10;
        $rule = Rule::allow('/api/users')
            ->when('age', $callback);

        expect($rule->getConditions())->toBe(['age' => $callback]);
    });

    test('when() adds multiple conditions', function (): void {
        $rule = Rule::allow('/api/users')
            ->when('role', 'admin')
            ->when('active', true);

        expect($rule->getConditions())->toBe([
            'role' => 'admin',
            'active' => true,
        ]);
    });

    test('when() returns new instance (immutability)', function (): void {
        $rule1 = Rule::allow('/api/users');
        $rule2 = $rule1->when('role', 'admin');

        expect($rule1)->not->toBe($rule2)
            ->and($rule1->getConditions())->toBe([])
            ->and($rule2->getConditions())->toBe(['role' => 'admin']);
    });
});

describe('Rule matchesPath() with wildcards and variables', function (): void {
    test('matchesPath() matches exact path', function (): void {
        $rule = Rule::allow('/api/users');

        expect($rule->matchesPath('/api/users'))->toBeTrue()
            ->and($rule->matchesPath('/api/posts'))->toBeFalse();
    });

    test('matchesPath() matches single wildcard', function (): void {
        $rule = Rule::allow('/api/*/settings');

        expect($rule->matchesPath('/api/users/settings'))->toBeTrue()
            ->and($rule->matchesPath('/api/posts/settings'))->toBeTrue()
            ->and($rule->matchesPath('/api/users/profile/settings'))->toBeFalse();
    });

    test('matchesPath() matches glob wildcard', function (): void {
        $rule = Rule::allow('/api/**');

        expect($rule->matchesPath('/api/users'))->toBeTrue()
            ->and($rule->matchesPath('/api/users/123'))->toBeTrue()
            ->and($rule->matchesPath('/api/users/123/settings'))->toBeTrue()
            ->and($rule->matchesPath('/other'))->toBeFalse();
    });

    test('matchesPath() matches path with variable', function (): void {
        $rule = Rule::allow('/api/users/${id}');

        expect($rule->matchesPath('/api/users/123', ['id' => '123']))->toBeTrue()
            ->and($rule->matchesPath('/api/users/456', ['id' => '456']))->toBeTrue()
            ->and($rule->matchesPath('/api/users/123', ['id' => '456']))->toBeFalse();
    });

    test('matchesPath() matches complex pattern with multiple variables', function (): void {
        $rule = Rule::allow('/api/customers/${customer_id}/orders/${order_id}');

        expect($rule->matchesPath(
            '/api/customers/cust-123/orders/order-456',
            ['customer_id' => 'cust-123', 'order_id' => 'order-456'],
        ))->toBeTrue();

        expect($rule->matchesPath(
            '/api/customers/cust-123/orders/order-456',
            ['customer_id' => 'wrong', 'order_id' => 'order-456'],
        ))->toBeFalse();
    });

    test('matchesPath() normalizes paths', function (): void {
        $rule = Rule::allow('/api/users/');

        expect($rule->matchesPath('/api/users'))->toBeTrue()
            ->and($rule->matchesPath('/api/users/'))->toBeTrue();
    });
});

describe('Rule hasCapability() including Admin implies all', function (): void {
    test('hasCapability() returns true for exact capability match', function (): void {
        $rule = Rule::allow('/api/users')
            ->capabilities(Capability::Read);

        expect($rule->hasCapability(Capability::Read))->toBeTrue()
            ->and($rule->hasCapability(Capability::Update))->toBeFalse();
    });

    test('hasCapability() returns true for any capability when Admin is set', function (): void {
        $rule = Rule::allow('/api/users')
            ->capabilities(Capability::Admin);

        expect($rule->hasCapability(Capability::Read))->toBeTrue()
            ->and($rule->hasCapability(Capability::List))->toBeTrue()
            ->and($rule->hasCapability(Capability::Create))->toBeTrue()
            ->and($rule->hasCapability(Capability::Update))->toBeTrue()
            ->and($rule->hasCapability(Capability::Delete))->toBeTrue()
            ->and($rule->hasCapability(Capability::Admin))->toBeTrue();
    });

    test('hasCapability() returns true when capability is in list', function (): void {
        $rule = Rule::allow('/api/users')
            ->capabilities(Capability::Read, Capability::Update);

        expect($rule->hasCapability(Capability::Read))->toBeTrue()
            ->and($rule->hasCapability(Capability::Update))->toBeTrue()
            ->and($rule->hasCapability(Capability::Delete))->toBeFalse();
    });

    test('hasCapability() returns false when no capabilities set', function (): void {
        $rule = Rule::allow('/api/users');

        expect($rule->hasCapability(Capability::Read))->toBeFalse();
    });
});

describe('Rule conditionsSatisfied() evaluation', function (): void {
    test('conditionsSatisfied() returns true when no conditions set', function (): void {
        $rule = Rule::allow('/api/users');

        expect($rule->conditionsSatisfied([]))->toBeTrue()
            ->and($rule->conditionsSatisfied(['role' => 'admin']))->toBeTrue();
    });

    test('conditionsSatisfied() evaluates equals condition', function (): void {
        $rule = Rule::allow('/api/users')
            ->when('role', 'admin');

        expect($rule->conditionsSatisfied(['role' => 'admin']))->toBeTrue()
            ->and($rule->conditionsSatisfied(['role' => 'user']))->toBeFalse();
    });

    test('conditionsSatisfied() evaluates array condition', function (): void {
        $rule = Rule::allow('/api/users')
            ->when('role', ['admin', 'moderator']);

        expect($rule->conditionsSatisfied(['role' => 'admin']))->toBeTrue()
            ->and($rule->conditionsSatisfied(['role' => 'moderator']))->toBeTrue()
            ->and($rule->conditionsSatisfied(['role' => 'user']))->toBeFalse();
    });

    test('conditionsSatisfied() evaluates callable condition', function (): void {
        $rule = Rule::allow('/api/users')
            ->when('age', fn ($value): bool => $value >= 18);

        expect($rule->conditionsSatisfied(['age' => 25]))->toBeTrue()
            ->and($rule->conditionsSatisfied(['age' => 18]))->toBeTrue()
            ->and($rule->conditionsSatisfied(['age' => 17]))->toBeFalse();
    });

    test('conditionsSatisfied() evaluates multiple conditions (all must match)', function (): void {
        $rule = Rule::allow('/api/users')
            ->when('role', 'admin')
            ->when('active', true);

        expect($rule->conditionsSatisfied(['role' => 'admin', 'active' => true]))->toBeTrue()
            ->and($rule->conditionsSatisfied(['role' => 'admin', 'active' => false]))->toBeFalse()
            ->and($rule->conditionsSatisfied(['role' => 'user', 'active' => true]))->toBeFalse();
    });

    test('conditionsSatisfied() returns false when context missing field', function (): void {
        $rule = Rule::allow('/api/users')
            ->when('role', 'admin');

        expect($rule->conditionsSatisfied([]))->toBeFalse()
            ->and($rule->conditionsSatisfied(['other' => 'value']))->toBeFalse();
    });
});

describe('Rule fromArray() / toArray() / jsonSerialize()', function (): void {
    test('fromArray() creates rule with minimal data', function (): void {
        $data = [
            'path' => '/api/users',
        ];

        $rule = Rule::fromArray($data);

        expect($rule->getPath())->toBe('/api/users')
            ->and($rule->getEffect())->toBe(Effect::Allow)
            ->and($rule->getCapabilities())->toBe([])
            ->and($rule->getConditions())->toBe([])
            ->and($rule->getDescription())->toBeNull();
    });

    test('fromArray() creates rule with all data', function (): void {
        $data = [
            'path' => '/api/users',
            'effect' => 'deny',
            'capabilities' => ['read', 'update'],
            'conditions' => ['role' => 'admin'],
            'description' => 'Admin access to users',
        ];

        $rule = Rule::fromArray($data);

        expect($rule->getPath())->toBe('/api/users')
            ->and($rule->getEffect())->toBe(Effect::Deny)
            ->and($rule->getCapabilities())->toBe([Capability::Read, Capability::Update])
            ->and($rule->getConditions())->toBe(['role' => 'admin'])
            ->and($rule->getDescription())->toBe('Admin access to users');
    });

    test('toArray() exports rule with all properties', function (): void {
        $rule = Rule::allow('/api/users')
            ->capabilities(Capability::Read, Capability::Update)
            ->when('role', 'admin')
            ->description('User management');

        $array = $rule->toArray();

        expect($array)->toBe([
            'path' => '/api/users',
            'effect' => 'allow',
            'capabilities' => ['read', 'update'],
            'conditions' => ['role' => 'admin'],
            'description' => 'User management',
        ]);
    });

    test('toArray() exports rule with minimal properties', function (): void {
        $rule = Rule::deny('/api/admin');

        $array = $rule->toArray();

        expect($array)->toBe([
            'path' => '/api/admin',
            'effect' => 'deny',
            'capabilities' => [],
            'conditions' => [],
            'description' => null,
        ]);
    });

    test('jsonSerialize() returns same as toArray()', function (): void {
        $rule = Rule::allow('/api/users')
            ->capabilities(Capability::Read)
            ->when('role', 'admin');

        expect($rule->jsonSerialize())->toBe($rule->toArray());
    });

    test('round-trip conversion preserves rule data', function (): void {
        $original = Rule::allow('/api/users/${id}')
            ->capabilities(Capability::Read, Capability::Update)
            ->when('role', ['admin', 'moderator'])
            ->description('User access');

        $array = $original->toArray();
        $restored = Rule::fromArray($array);

        expect($restored->getPath())->toBe($original->getPath())
            ->and($restored->getEffect())->toBe($original->getEffect())
            ->and($restored->getCapabilities())->toBe($original->getCapabilities())
            ->and($restored->getConditions())->toBe($original->getConditions())
            ->and($restored->getDescription())->toBe($original->getDescription());
    });
});

describe('Rule description() method', function (): void {
    test('description() sets description', function (): void {
        $rule = Rule::allow('/api/users')
            ->description('User management endpoint');

        expect($rule->getDescription())->toBe('User management endpoint');
    });

    test('description() returns new instance (immutability)', function (): void {
        $rule1 = Rule::allow('/api/users');
        $rule2 = $rule1->description('Test description');

        expect($rule1)->not->toBe($rule2)
            ->and($rule1->getDescription())->toBeNull()
            ->and($rule2->getDescription())->toBe('Test description');
    });

    test('description() can be chained with other fluent methods', function (): void {
        $rule = Rule::allow('/api/users')
            ->capabilities(Capability::Read)
            ->when('role', 'admin')
            ->description('Admin read access');

        expect($rule->getDescription())->toBe('Admin read access')
            ->and($rule->getCapabilities())->toBe([Capability::Read])
            ->and($rule->getConditions())->toBe(['role' => 'admin']);
    });
});

describe('Rule getters', function (): void {
    test('getPath() returns rule path', function (): void {
        $rule = Rule::allow('/api/users');

        expect($rule->getPath())->toBe('/api/users');
    });

    test('getEffect() returns rule effect', function (): void {
        $allowRule = Rule::allow('/api/users');
        $denyRule = Rule::deny('/api/admin');

        expect($allowRule->getEffect())->toBe(Effect::Allow)
            ->and($denyRule->getEffect())->toBe(Effect::Deny);
    });

    test('getCapabilities() returns empty array by default', function (): void {
        $rule = Rule::allow('/api/users');

        expect($rule->getCapabilities())->toBe([]);
    });

    test('getConditions() returns empty array by default', function (): void {
        $rule = Rule::allow('/api/users');

        expect($rule->getConditions())->toBe([]);
    });

    test('getDescription() returns null by default', function (): void {
        $rule = Rule::allow('/api/users');

        expect($rule->getDescription())->toBeNull();
    });
});
