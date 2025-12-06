<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\Capability;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Rule;
use Symfony\Component\Yaml\Yaml;

describe('Policy factory', function (): void {
    test('create() factory creates policy with name', function (): void {
        $policy = Policy::create('user-policy');

        expect($policy->getName())->toBe('user-policy')
            ->and($policy->getDescription())->toBe('')
            ->and($policy->getRules())->toBe([]);
    });
});

describe('Policy fluent API', function (): void {
    test('name() sets policy name', function (): void {
        $policy = Policy::create('initial-name')
            ->name('updated-name');

        expect($policy->getName())->toBe('updated-name');
    });

    test('name() returns new instance (immutability)', function (): void {
        $policy1 = Policy::create('original');
        $policy2 = $policy1->name('updated');

        expect($policy1)->not->toBe($policy2)
            ->and($policy1->getName())->toBe('original')
            ->and($policy2->getName())->toBe('updated');
    });

    test('description() sets policy description', function (): void {
        $policy = Policy::create('user-policy')
            ->description('Manages user access');

        expect($policy->getDescription())->toBe('Manages user access');
    });

    test('description() returns new instance (immutability)', function (): void {
        $policy1 = Policy::create('user-policy');
        $policy2 = $policy1->description('Test description');

        expect($policy1)->not->toBe($policy2)
            ->and($policy1->getDescription())->toBe('')
            ->and($policy2->getDescription())->toBe('Test description');
    });

    test('addRule() adds single rule to policy', function (): void {
        $rule = Rule::allow('/api/users');
        $policy = Policy::create('user-policy')
            ->addRule($rule);

        expect($policy->getRules())->toBe([$rule]);
    });

    test('addRule() adds multiple rules to policy', function (): void {
        $rule1 = Rule::allow('/api/users');
        $rule2 = Rule::deny('/api/admin');

        $policy = Policy::create('user-policy')
            ->addRule($rule1)
            ->addRule($rule2);

        expect($policy->getRules())->toBe([$rule1, $rule2]);
    });

    test('addRule() returns new instance (immutability)', function (): void {
        $rule = Rule::allow('/api/users');
        $policy1 = Policy::create('user-policy');
        $policy2 = $policy1->addRule($rule);

        expect($policy1)->not->toBe($policy2)
            ->and($policy1->getRules())->toBe([])
            ->and($policy2->getRules())->toBe([$rule]);
    });

    test('fluent methods can be chained', function (): void {
        $rule1 = Rule::allow('/api/users')->capabilities(Capability::Read);
        $rule2 = Rule::deny('/api/admin');

        $policy = Policy::create('initial-name')
            ->name('user-policy')
            ->description('User management policy')
            ->addRule($rule1)
            ->addRule($rule2);

        expect($policy->getName())->toBe('user-policy')
            ->and($policy->getDescription())->toBe('User management policy')
            ->and($policy->getRules())->toBe([$rule1, $rule2]);
    });
});

describe('Policy getters', function (): void {
    test('getName() returns policy name', function (): void {
        $policy = Policy::create('user-policy');

        expect($policy->getName())->toBe('user-policy');
    });

    test('getDescription() returns policy description', function (): void {
        $policy = Policy::create('user-policy')
            ->description('User access management');

        expect($policy->getDescription())->toBe('User access management');
    });

    test('getDescription() returns empty string by default', function (): void {
        $policy = Policy::create('user-policy');

        expect($policy->getDescription())->toBe('');
    });

    test('getRules() returns all rules', function (): void {
        $rule1 = Rule::allow('/api/users');
        $rule2 = Rule::deny('/api/admin');

        $policy = Policy::create('user-policy')
            ->addRule($rule1)
            ->addRule($rule2);

        expect($policy->getRules())->toBe([$rule1, $rule2]);
    });

    test('getRules() returns empty array by default', function (): void {
        $policy = Policy::create('user-policy');

        expect($policy->getRules())->toBe([]);
    });
});

describe('Policy fromArray() / toArray() / jsonSerialize()', function (): void {
    test('fromArray() creates policy with minimal data', function (): void {
        $data = [
            'name' => 'user-policy',
        ];

        $policy = Policy::fromArray($data);

        expect($policy->getName())->toBe('user-policy')
            ->and($policy->getDescription())->toBe('')
            ->and($policy->getRules())->toBe([]);
    });

    test('fromArray() creates policy with description', function (): void {
        $data = [
            'name' => 'user-policy',
            'description' => 'Manages user access',
        ];

        $policy = Policy::fromArray($data);

        expect($policy->getName())->toBe('user-policy')
            ->and($policy->getDescription())->toBe('Manages user access');
    });

    test('fromArray() creates policy with rules', function (): void {
        $data = [
            'name' => 'user-policy',
            'description' => 'User management',
            'rules' => [
                [
                    'path' => '/api/users',
                    'effect' => 'allow',
                    'capabilities' => ['read', 'list'],
                ],
                [
                    'path' => '/api/admin',
                    'effect' => 'deny',
                ],
            ],
        ];

        $policy = Policy::fromArray($data);

        expect($policy->getName())->toBe('user-policy')
            ->and($policy->getDescription())->toBe('User management')
            ->and($policy->getRules())->toHaveCount(2);

        $rules = $policy->getRules();
        expect($rules[0]->getPath())->toBe('/api/users')
            ->and($rules[0]->getCapabilities())->toBe([Capability::Read, Capability::List])
            ->and($rules[1]->getPath())->toBe('/api/admin');
    });

    test('toArray() exports policy with all properties', function (): void {
        $policy = Policy::create('user-policy')
            ->description('User management')
            ->addRule(Rule::allow('/api/users')->capabilities(Capability::Read))
            ->addRule(Rule::deny('/api/admin'));

        $array = $policy->toArray();

        expect($array)->toHaveKey('name', 'user-policy')
            ->and($array)->toHaveKey('description', 'User management')
            ->and($array['rules'])->toHaveCount(2)
            ->and($array['rules'][0]['path'])->toBe('/api/users')
            ->and($array['rules'][1]['path'])->toBe('/api/admin');
    });

    test('toArray() exports policy with minimal properties', function (): void {
        $policy = Policy::create('simple-policy');

        $array = $policy->toArray();

        expect($array)->toBe([
            'name' => 'simple-policy',
            'description' => '',
            'rules' => [],
        ]);
    });

    test('jsonSerialize() returns same as toArray()', function (): void {
        $policy = Policy::create('user-policy')
            ->description('Test policy')
            ->addRule(Rule::allow('/api/users'));

        expect($policy->jsonSerialize())->toBe($policy->toArray());
    });

    test('round-trip conversion preserves policy data', function (): void {
        $original = Policy::create('user-policy')
            ->description('User management policy')
            ->addRule(
                Rule::allow('/api/users/${id}')
                    ->capabilities(Capability::Read, Capability::Update)
                    ->when('role', 'admin'),
            )
            ->addRule(Rule::deny('/api/admin'));

        $array = $original->toArray();
        $restored = Policy::fromArray($array);

        expect($restored->getName())->toBe($original->getName())
            ->and($restored->getDescription())->toBe($original->getDescription())
            ->and($restored->getRules())->toHaveCount(2);

        $originalRules = $original->getRules();
        $restoredRules = $restored->getRules();

        expect($restoredRules[0]->getPath())->toBe($originalRules[0]->getPath())
            ->and($restoredRules[0]->getCapabilities())->toBe($originalRules[0]->getCapabilities())
            ->and($restoredRules[0]->getConditions())->toBe($originalRules[0]->getConditions());
    });
});

describe('Policy fromJson() loading', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/arbiter-tests-'.uniqid();
        mkdir($this->tempDir);
    });

    afterEach(function (): void {
        // Clean up temp files
        $files = glob($this->tempDir.'/*');

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }

        rmdir($this->tempDir);
    });

    test('fromJson() loads policy from JSON file', function (): void {
        $jsonPath = $this->tempDir.'/policy.json';
        $data = [
            'name' => 'user-policy',
            'description' => 'User management',
            'rules' => [
                [
                    'path' => '/api/users',
                    'effect' => 'allow',
                    'capabilities' => ['read'],
                ],
            ],
        ];
        file_put_contents($jsonPath, json_encode($data));

        $policy = Policy::fromJson($jsonPath);

        expect($policy->getName())->toBe('user-policy')
            ->and($policy->getDescription())->toBe('User management')
            ->and($policy->getRules())->toHaveCount(1);
    });

    test('fromJson() throws exception when file not found', function (): void {
        expect(fn (): Policy => Policy::fromJson('/nonexistent/path.json'))
            ->toThrow(RuntimeException::class, 'JSON file not found');
    });

    test('fromJson() throws exception when JSON is invalid', function (): void {
        $jsonPath = $this->tempDir.'/invalid.json';
        file_put_contents($jsonPath, 'invalid json content');

        expect(fn (): Policy => Policy::fromJson($jsonPath))
            ->toThrow(RuntimeException::class, 'Invalid JSON');
    });

    test('fromJson() throws exception when JSON is not array', function (): void {
        $jsonPath = $this->tempDir.'/string.json';
        file_put_contents($jsonPath, json_encode('just a string'));

        expect(fn (): Policy => Policy::fromJson($jsonPath))
            ->toThrow(RuntimeException::class, 'Invalid JSON');
    });

    test('fromJson() loads complex policy structure', function (): void {
        $jsonPath = $this->tempDir.'/complex-policy.json';
        $data = [
            'name' => 'complex-policy',
            'description' => 'Complex access control',
            'rules' => [
                [
                    'path' => '/api/users/${id}',
                    'effect' => 'allow',
                    'capabilities' => ['read', 'update'],
                    'conditions' => ['role' => ['admin', 'moderator']],
                    'description' => 'Admin user access',
                ],
                [
                    'path' => '/api/admin/**',
                    'effect' => 'deny',
                    'capabilities' => ['delete'],
                ],
            ],
        ];
        file_put_contents($jsonPath, json_encode($data, \JSON_PRETTY_PRINT));

        $policy = Policy::fromJson($jsonPath);

        expect($policy->getName())->toBe('complex-policy')
            ->and($policy->getRules())->toHaveCount(2);

        $rules = $policy->getRules();
        expect($rules[0]->getPath())->toBe('/api/users/${id}')
            ->and($rules[0]->getConditions())->toBe(['role' => ['admin', 'moderator']])
            ->and($rules[0]->getDescription())->toBe('Admin user access');
    });
});

describe('Policy fromYaml() loading', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/arbiter-tests-'.uniqid();
        mkdir($this->tempDir);
    });

    afterEach(function (): void {
        // Clean up temp files
        $files = glob($this->tempDir.'/*');

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }

        rmdir($this->tempDir);
    });

    test('fromYaml() loads policy from YAML file', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $yamlPath = $this->tempDir.'/policy.yaml';
        $yaml = <<<'YAML'
name: user-policy
description: User management
rules:
  - path: /api/users
    effect: allow
    capabilities: [read]
YAML;
        file_put_contents($yamlPath, $yaml);

        $policy = Policy::fromYaml($yamlPath);

        expect($policy->getName())->toBe('user-policy')
            ->and($policy->getDescription())->toBe('User management')
            ->and($policy->getRules())->toHaveCount(1);
    });

    test('fromYaml() throws exception when file not found', function (): void {
        expect(fn (): Policy => Policy::fromYaml('/nonexistent/path.yaml'))
            ->toThrow(RuntimeException::class, 'YAML file not found');
    });

    test('fromYaml() throws exception when symfony/yaml not installed', function (): void {
        if (class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml is installed');
        }

        $yamlPath = $this->tempDir.'/policy.yaml';
        file_put_contents($yamlPath, 'name: test');

        expect(fn (): Policy => Policy::fromYaml($yamlPath))
            ->toThrow(RuntimeException::class, 'symfony/yaml is required');
    });

    test('fromYaml() loads complex policy structure', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $yamlPath = $this->tempDir.'/complex-policy.yaml';
        $yaml = <<<'YAML'
name: complex-policy
description: Complex access control
rules:
  - path: /api/users/${id}
    effect: allow
    capabilities: [read, update]
    conditions:
      role: [admin, moderator]
    description: Admin user access
  - path: /api/admin/**
    effect: deny
    capabilities: [delete]
YAML;
        file_put_contents($yamlPath, $yaml);

        $policy = Policy::fromYaml($yamlPath);

        expect($policy->getName())->toBe('complex-policy')
            ->and($policy->getRules())->toHaveCount(2);

        $rules = $policy->getRules();
        expect($rules[0]->getPath())->toBe('/api/users/${id}')
            ->and($rules[0]->getConditions())->toBe(['role' => ['admin', 'moderator']])
            ->and($rules[0]->getDescription())->toBe('Admin user access');
    });
});
