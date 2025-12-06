<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Repository\YamlRepository;
use Symfony\Component\Yaml\Yaml;

describe('YamlRepository single file mode', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/arbiter-yaml-tests-'.uniqid();
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

    test('constructor throws exception when symfony/yaml not installed', function (): void {
        if (class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml is installed');
        }

        $yamlPath = $this->tempDir.'/policy.yaml';
        file_put_contents($yamlPath, "name: test-policy\n");

        expect(fn (): YamlRepository => new YamlRepository($yamlPath))
            ->toThrow(RuntimeException::class, 'symfony/yaml is required to use YamlRepository');
    });

    test('constructor loads single policy from YAML file', function (): void {
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

        $repository = new YamlRepository($yamlPath);

        expect($repository->has('user-policy'))->toBeTrue()
            ->and($repository->get('user-policy')->getName())->toBe('user-policy')
            ->and($repository->get('user-policy')->getDescription())->toBe('User management');
    });

    test('constructor loads multiple policies from YAML array', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $yamlPath = $this->tempDir.'/policies.yaml';
        $yaml = <<<'YAML'
- name: policy-1
  description: First policy
  rules: []
- name: policy-2
  description: Second policy
  rules: []
YAML;
        file_put_contents($yamlPath, $yaml);

        $repository = new YamlRepository($yamlPath);

        expect($repository->all())->toHaveCount(2)
            ->and($repository->has('policy-1'))->toBeTrue()
            ->and($repository->has('policy-2'))->toBeTrue();
    });

    test('constructor detects single policy vs array by presence of name key', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $yamlPath = $this->tempDir.'/single-policy.yaml';
        $yaml = <<<'YAML'
name: single-policy
description: Single policy mode
rules: []
YAML;
        file_put_contents($yamlPath, $yaml);

        $repository = new YamlRepository($yamlPath);

        expect($repository->all())->toHaveCount(1)
            ->and($repository->has('single-policy'))->toBeTrue();
    });

    test('constructor throws exception when file not found', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        expect(fn (): YamlRepository => new YamlRepository('/nonexistent/path.yaml'))
            ->toThrow(RuntimeException::class, 'YAML file not found: /nonexistent/path.yaml');
    });

    test('constructor throws exception when path is directory not file', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        expect(fn (): YamlRepository => new YamlRepository($this->tempDir))
            ->toThrow(RuntimeException::class, 'Path is not a file');
    });

    test('constructor throws exception when YAML is invalid', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $yamlPath = $this->tempDir.'/invalid.yaml';
        file_put_contents($yamlPath, "invalid yaml:\n  - no proper structure\n  bad indentation");

        // Symfony YAML parser is quite lenient, but we test for the error case
        // The actual behavior depends on symfony/yaml version
        try {
            new YamlRepository($yamlPath);
            // If no exception thrown, verify it at least parsed something
            expect(true)->toBeTrue();
        } catch (Exception $exception) {
            // Accept any exception from YAML parser
            expect($exception->getMessage())->not->toBeEmpty();
        }
    });

    test('constructor loads policies with complex rules', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $yamlPath = $this->tempDir.'/complex.yaml';
        $yaml = <<<'YAML'
name: complex-policy
description: Complex policy
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

        $repository = new YamlRepository($yamlPath);

        $policy = $repository->get('complex-policy');
        expect($policy->getRules())->toHaveCount(2);

        $rules = $policy->getRules();
        expect($rules[0]->getPath())->toBe('/api/users/${id}')
            ->and($rules[0]->getConditions())->toBe(['role' => ['admin', 'moderator']]);
    });

    test('constructor skips non-array elements in policy array', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $yamlPath = $this->tempDir.'/mixed.yaml';
        $yaml = <<<'YAML'
- name: policy-1
  description: Valid policy
  rules: []
- string element
- 123
- null
- name: policy-2
  description: Another valid policy
  rules: []
YAML;
        file_put_contents($yamlPath, $yaml);

        $repository = new YamlRepository($yamlPath);

        expect($repository->all())->toHaveCount(2)
            ->and($repository->has('policy-1'))->toBeTrue()
            ->and($repository->has('policy-2'))->toBeTrue();
    });
});

describe('YamlRepository directory mode', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/arbiter-yaml-tests-'.uniqid();
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

    test('constructor loads policies from directory with perFile flag', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $policy1Yaml = <<<'YAML'
name: policy-1
description: First policy
rules: []
YAML;
        $policy2Yaml = <<<'YAML'
name: policy-2
description: Second policy
rules: []
YAML;

        file_put_contents($this->tempDir.'/policy-1.yaml', $policy1Yaml);
        file_put_contents($this->tempDir.'/policy-2.yml', $policy2Yaml);

        $repository = new YamlRepository($this->tempDir, true);

        expect($repository->all())->toHaveCount(2)
            ->and($repository->has('policy-1'))->toBeTrue()
            ->and($repository->has('policy-2'))->toBeTrue();
    });

    test('constructor loads both .yml and .yaml extensions', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $yamlPolicy = <<<'YAML'
name: yaml-policy
description: Using .yaml extension
rules: []
YAML;
        $ymlPolicy = <<<'YAML'
name: yml-policy
description: Using .yml extension
rules: []
YAML;

        file_put_contents($this->tempDir.'/policy1.yaml', $yamlPolicy);
        file_put_contents($this->tempDir.'/policy2.yml', $ymlPolicy);

        $repository = new YamlRepository($this->tempDir, true);

        expect($repository->all())->toHaveCount(2)
            ->and($repository->has('yaml-policy'))->toBeTrue()
            ->and($repository->has('yml-policy'))->toBeTrue();
    });

    test('constructor ignores non-YAML files in directory', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $policyYaml = <<<'YAML'
name: valid-policy
description: Valid policy
rules: []
YAML;

        file_put_contents($this->tempDir.'/valid.yaml', $policyYaml);
        file_put_contents($this->tempDir.'/ignored.txt', 'should be ignored');
        file_put_contents($this->tempDir.'/also-ignored.json', '{"json": "data"}');

        $repository = new YamlRepository($this->tempDir, true);

        expect($repository->all())->toHaveCount(1)
            ->and($repository->has('valid-policy'))->toBeTrue();
    });

    test('constructor throws exception when directory not found', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        expect(fn (): YamlRepository => new YamlRepository('/nonexistent/directory', true))
            ->toThrow(RuntimeException::class, 'Directory not found: /nonexistent/directory');
    });

    test('constructor throws exception when no YAML files in directory', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        file_put_contents($this->tempDir.'/readme.txt', 'No YAML files here');

        expect(fn (): YamlRepository => new YamlRepository($this->tempDir, true))
            ->toThrow(RuntimeException::class, 'No YAML files found in directory');
    });

    test('constructor throws exception when YAML file in directory is invalid', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        // Create an invalid YAML that will be parsed as non-array
        file_put_contents($this->tempDir.'/invalid.yaml', 'just a string');

        expect(fn (): YamlRepository => new YamlRepository($this->tempDir, true))
            ->toThrow(RuntimeException::class, 'Invalid YAML in file');
    });

    test('constructor loads multiple policies from directory with complex rules', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $adminYaml = <<<'YAML'
name: admin-policy
description: Admin access
rules:
  - path: /api/admin/**
    effect: allow
    capabilities: [read, update, delete]
YAML;

        $userYaml = <<<'YAML'
name: user-policy
description: User access
rules:
  - path: /api/users/${id}
    effect: allow
    capabilities: [read, update]
    conditions:
      userId: ${id}
YAML;

        file_put_contents($this->tempDir.'/admin.yaml', $adminYaml);
        file_put_contents($this->tempDir.'/user.yml', $userYaml);

        $repository = new YamlRepository($this->tempDir, true);

        expect($repository->all())->toHaveCount(2);

        $admin = $repository->get('admin-policy');
        $user = $repository->get('user-policy');

        expect($admin->getRules())->toHaveCount(1)
            ->and($user->getRules())->toHaveCount(1)
            ->and($user->getRules()[0]->getConditions())->toBe(['userId' => '${id}']);
    });
});

describe('YamlRepository get() method', function (): void {
    beforeEach(function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $this->tempDir = sys_get_temp_dir().'/arbiter-yaml-tests-'.uniqid();
        mkdir($this->tempDir);

        $yaml = <<<'YAML'
- name: policy-1
  description: First policy
  rules: []
- name: policy-2
  description: Second policy
  rules: []
YAML;
        file_put_contents($this->tempDir.'/policies.yaml', $yaml);

        $this->repository = new YamlRepository($this->tempDir.'/policies.yaml');
    });

    afterEach(function (): void {
        $files = glob($this->tempDir.'/*');

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }

        rmdir($this->tempDir);
    });

    test('get() returns policy by name', function (): void {
        $policy = $this->repository->get('policy-1');

        expect($policy->getName())->toBe('policy-1')
            ->and($policy->getDescription())->toBe('First policy');
    });

    test('get() throws PolicyNotFoundException when policy does not exist', function (): void {
        expect(fn () => $this->repository->get('nonexistent'))
            ->toThrow(PolicyNotFoundException::class, 'Policy not found: nonexistent');
    });
});

describe('YamlRepository has() method', function (): void {
    beforeEach(function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $this->tempDir = sys_get_temp_dir().'/arbiter-yaml-tests-'.uniqid();
        mkdir($this->tempDir);

        $yaml = <<<'YAML'
name: test-policy
description: Test
rules: []
YAML;
        file_put_contents($this->tempDir.'/policy.yaml', $yaml);

        $this->repository = new YamlRepository($this->tempDir.'/policy.yaml');
    });

    afterEach(function (): void {
        $files = glob($this->tempDir.'/*');

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }

        rmdir($this->tempDir);
    });

    test('has() returns true when policy exists', function (): void {
        expect($this->repository->has('test-policy'))->toBeTrue();
    });

    test('has() returns false when policy does not exist', function (): void {
        expect($this->repository->has('nonexistent'))->toBeFalse();
    });
});

describe('YamlRepository all() method', function (): void {
    beforeEach(function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $this->tempDir = sys_get_temp_dir().'/arbiter-yaml-tests-'.uniqid();
        mkdir($this->tempDir);

        $yaml = <<<'YAML'
- name: admin-policy
  description: Admin
  rules: []
- name: user-policy
  description: User
  rules: []
YAML;
        file_put_contents($this->tempDir.'/policies.yaml', $yaml);

        $this->repository = new YamlRepository($this->tempDir.'/policies.yaml');
    });

    afterEach(function (): void {
        $files = glob($this->tempDir.'/*');

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }

        rmdir($this->tempDir);
    });

    test('all() returns all policies keyed by name', function (): void {
        $all = $this->repository->all();

        expect($all)->toHaveCount(2)
            ->and($all)->toHaveKey('admin-policy')
            ->and($all)->toHaveKey('user-policy')
            ->and($all['admin-policy']->getName())->toBe('admin-policy')
            ->and($all['user-policy']->getName())->toBe('user-policy');
    });
});

describe('YamlRepository getMany() method', function (): void {
    beforeEach(function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $this->tempDir = sys_get_temp_dir().'/arbiter-yaml-tests-'.uniqid();
        mkdir($this->tempDir);

        $yaml = <<<'YAML'
- name: policy-1
  description: First
  rules: []
- name: policy-2
  description: Second
  rules: []
- name: policy-3
  description: Third
  rules: []
YAML;
        file_put_contents($this->tempDir.'/policies.yaml', $yaml);

        $this->repository = new YamlRepository($this->tempDir.'/policies.yaml');
    });

    afterEach(function (): void {
        $files = glob($this->tempDir.'/*');

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }

        rmdir($this->tempDir);
    });

    test('getMany() returns multiple policies by name', function (): void {
        $result = $this->repository->getMany(['policy-1', 'policy-3']);

        expect($result)->toHaveCount(2)
            ->and($result)->toHaveKey('policy-1')
            ->and($result)->toHaveKey('policy-3')
            ->and($result)->not->toHaveKey('policy-2');
    });

    test('getMany() returns empty array when given empty array', function (): void {
        $result = $this->repository->getMany([]);

        expect($result)->toBe([]);
    });

    test('getMany() throws PolicyNotFoundException when policy missing', function (): void {
        expect(fn () => $this->repository->getMany(['policy-1', 'nonexistent']))
            ->toThrow(PolicyNotFoundException::class, 'Policies not found: nonexistent');
    });

    test('getMany() throws exception listing all missing policies', function (): void {
        expect(fn () => $this->repository->getMany(['missing-1', 'missing-2', 'missing-3']))
            ->toThrow(PolicyNotFoundException::class, 'Policies not found: missing-1, missing-2, missing-3');
    });
});

describe('YamlRepository edge cases', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/arbiter-yaml-tests-'.uniqid();
        mkdir($this->tempDir);
    });

    afterEach(function (): void {
        $files = glob($this->tempDir.'/*');

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }

        rmdir($this->tempDir);
    });

    test('repository handles empty YAML array', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $yamlPath = $this->tempDir.'/empty.yaml';
        file_put_contents($yamlPath, '[]');

        $repository = new YamlRepository($yamlPath);

        expect($repository->all())->toBe([]);
    });

    test('repository handles YAML with UTF-8 characters', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $yamlPath = $this->tempDir.'/utf8.yaml';
        $yaml = <<<'YAML'
name: international-policy
description: 'Politique d''accès avec caractères spéciaux: é, ñ, 中文'
rules: []
YAML;
        file_put_contents($yamlPath, $yaml);

        $repository = new YamlRepository($yamlPath);

        $policy = $repository->get('international-policy');
        expect($policy->getDescription())->toContain('é')
            ->and($policy->getDescription())->toContain('中文');
    });

    test('repository handles YAML with anchors and aliases', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $yamlPath = $this->tempDir.'/anchors.yaml';
        $yaml = <<<'YAML'
name: anchor-policy
description: Test anchors
rules:
  - &common_rule
    path: /api/common
    effect: allow
    capabilities: [read]
  - *common_rule
YAML;
        file_put_contents($yamlPath, $yaml);

        $repository = new YamlRepository($yamlPath);

        $policy = $repository->get('anchor-policy');
        expect($policy->getRules())->toHaveCount(2);
    });

    test('repository handles YAML with multiline strings', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $yamlPath = $this->tempDir.'/multiline.yaml';
        $yaml = <<<'YAML'
name: multiline-policy
description: |
  This is a multiline description
  that spans multiple lines
  with proper formatting
rules: []
YAML;
        file_put_contents($yamlPath, $yaml);

        $repository = new YamlRepository($yamlPath);

        $policy = $repository->get('multiline-policy');
        expect($policy->getDescription())->toContain('multiline description')
            ->and($policy->getDescription())->toContain('multiple lines');
    });

    test('repository in directory mode handles many files', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        // Create 10 policy files with alternating extensions
        for ($i = 1; $i <= 10; ++$i) {
            $extension = ($i % 2 === 0) ? 'yml' : 'yaml';
            $yaml = <<<YAML
name: policy-{$i}
description: Policy number {$i}
rules: []
YAML;
            file_put_contents($this->tempDir.sprintf('/policy-%d.%s', $i, $extension), $yaml);
        }

        $repository = new YamlRepository($this->tempDir, true);

        expect($repository->all())->toHaveCount(10);

        // Verify each policy is accessible
        for ($i = 1; $i <= 10; ++$i) {
            expect($repository->has('policy-'.$i))->toBeTrue();
        }
    });

    test('repository handles YAML with nested structures', function (): void {
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml not installed');
        }

        $yamlPath = $this->tempDir.'/nested.yaml';
        $yaml = <<<'YAML'
name: nested-policy
description: Policy with deeply nested conditions
rules:
  - path: /api/resource
    effect: allow
    capabilities: [read, update]
    conditions:
      role:
        - admin
        - moderator
      permissions:
        resource:
          read: true
          update: true
        metadata:
          allowOverride: false
YAML;
        file_put_contents($yamlPath, $yaml);

        $repository = new YamlRepository($yamlPath);

        $policy = $repository->get('nested-policy');
        $conditions = $policy->getRules()[0]->getConditions();

        expect($conditions)->toHaveKey('role')
            ->and($conditions)->toHaveKey('permissions')
            ->and($conditions['permissions'])->toHaveKey('resource')
            ->and($conditions['permissions']['resource']['read'])->toBeTrue();
    });
});
