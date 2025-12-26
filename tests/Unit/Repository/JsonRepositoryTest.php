<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\Exception\MultiplePoliciesNotFoundException;
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Repository\JsonRepository;

describe('JsonRepository single file mode', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/arbiter-json-tests-'.uniqid();
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

    test('constructor loads single policy from JSON file', function (): void {
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

        $repository = new JsonRepository($jsonPath);

        expect($repository->has('user-policy'))->toBeTrue()
            ->and($repository->get('user-policy')->getName())->toBe('user-policy')
            ->and($repository->get('user-policy')->getDescription())->toBe('User management');
    });

    test('constructor loads multiple policies from JSON array', function (): void {
        $jsonPath = $this->tempDir.'/policies.json';
        $data = [
            [
                'name' => 'policy-1',
                'description' => 'First policy',
                'rules' => [],
            ],
            [
                'name' => 'policy-2',
                'description' => 'Second policy',
                'rules' => [],
            ],
        ];
        file_put_contents($jsonPath, json_encode($data));

        $repository = new JsonRepository($jsonPath);

        expect($repository->all())->toHaveCount(2)
            ->and($repository->has('policy-1'))->toBeTrue()
            ->and($repository->has('policy-2'))->toBeTrue();
    });

    test('constructor detects single policy vs array by presence of name key', function (): void {
        $jsonPath = $this->tempDir.'/single-policy.json';
        $data = [
            'name' => 'single-policy',
            'description' => 'Single policy mode',
            'rules' => [],
        ];
        file_put_contents($jsonPath, json_encode($data));

        $repository = new JsonRepository($jsonPath);

        expect($repository->all())->toHaveCount(1)
            ->and($repository->has('single-policy'))->toBeTrue();
    });

    test('constructor throws exception when file not found', function (): void {
        expect(fn (): JsonRepository => new JsonRepository('/nonexistent/path.json'))
            ->toThrow(RuntimeException::class, 'JSON file not found: /nonexistent/path.json');
    });

    test('constructor throws exception when path is directory not file', function (): void {
        expect(fn (): JsonRepository => new JsonRepository($this->tempDir))
            ->toThrow(RuntimeException::class, 'Path is not a file');
    });

    test('constructor throws exception when JSON is invalid', function (): void {
        $jsonPath = $this->tempDir.'/invalid.json';
        file_put_contents($jsonPath, 'invalid json content {{{');

        expect(fn (): JsonRepository => new JsonRepository($jsonPath))
            ->toThrow(RuntimeException::class, 'Invalid JSON in file');
    });

    test('constructor throws exception when JSON is not array', function (): void {
        $jsonPath = $this->tempDir.'/string.json';
        file_put_contents($jsonPath, json_encode('just a string'));

        expect(fn (): JsonRepository => new JsonRepository($jsonPath))
            ->toThrow(RuntimeException::class, 'Invalid JSON in file');
    });

    test('constructor loads policies with complex rules', function (): void {
        $jsonPath = $this->tempDir.'/complex.json';
        $data = [
            'name' => 'complex-policy',
            'description' => 'Complex policy',
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
        file_put_contents($jsonPath, json_encode($data));

        $repository = new JsonRepository($jsonPath);

        $policy = $repository->get('complex-policy');
        expect($policy->getRules())->toHaveCount(2);

        $rules = $policy->getRules();
        expect($rules[0]->getPath())->toBe('/api/users/${id}')
            ->and($rules[0]->getConditions())->toBe(['role' => ['admin', 'moderator']]);
    });

    test('constructor skips non-array elements in policy array', function (): void {
        $jsonPath = $this->tempDir.'/mixed.json';
        $data = [
            [
                'name' => 'policy-1',
                'description' => 'Valid policy',
                'rules' => [],
            ],
            'string element',
            123,
            null,
            [
                'name' => 'policy-2',
                'description' => 'Another valid policy',
                'rules' => [],
            ],
        ];
        file_put_contents($jsonPath, json_encode($data));

        $repository = new JsonRepository($jsonPath);

        expect($repository->all())->toHaveCount(2)
            ->and($repository->has('policy-1'))->toBeTrue()
            ->and($repository->has('policy-2'))->toBeTrue();
    });
});

describe('JsonRepository directory mode', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/arbiter-json-tests-'.uniqid();
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
        $policy1Data = [
            'name' => 'policy-1',
            'description' => 'First policy',
            'rules' => [],
        ];
        $policy2Data = [
            'name' => 'policy-2',
            'description' => 'Second policy',
            'rules' => [],
        ];

        file_put_contents($this->tempDir.'/policy-1.json', json_encode($policy1Data));
        file_put_contents($this->tempDir.'/policy-2.json', json_encode($policy2Data));

        $repository = new JsonRepository($this->tempDir, true);

        expect($repository->all())->toHaveCount(2)
            ->and($repository->has('policy-1'))->toBeTrue()
            ->and($repository->has('policy-2'))->toBeTrue();
    });

    test('constructor loads only .json files from directory', function (): void {
        $policyData = [
            'name' => 'valid-policy',
            'description' => 'Valid policy',
            'rules' => [],
        ];

        file_put_contents($this->tempDir.'/valid.json', json_encode($policyData));
        file_put_contents($this->tempDir.'/ignored.txt', 'should be ignored');
        file_put_contents($this->tempDir.'/also-ignored.xml', '<xml>data</xml>');

        $repository = new JsonRepository($this->tempDir, true);

        expect($repository->all())->toHaveCount(1)
            ->and($repository->has('valid-policy'))->toBeTrue();
    });

    test('constructor throws exception when directory not found', function (): void {
        expect(fn (): JsonRepository => new JsonRepository('/nonexistent/directory', true))
            ->toThrow(RuntimeException::class, 'Directory not found: /nonexistent/directory');
    });

    test('constructor throws exception when no JSON files in directory', function (): void {
        file_put_contents($this->tempDir.'/readme.txt', 'No JSON files here');

        expect(fn (): JsonRepository => new JsonRepository($this->tempDir, true))
            ->toThrow(RuntimeException::class, 'No JSON files found in directory');
    });

    test('constructor throws exception when JSON file in directory is invalid', function (): void {
        file_put_contents($this->tempDir.'/invalid.json', 'invalid json {{{');

        expect(fn (): JsonRepository => new JsonRepository($this->tempDir, true))
            ->toThrow(RuntimeException::class, 'Invalid JSON in file');
    });

    test('constructor loads multiple policies from directory with complex rules', function (): void {
        $adminPolicy = [
            'name' => 'admin-policy',
            'description' => 'Admin access',
            'rules' => [
                [
                    'path' => '/api/admin/**',
                    'effect' => 'allow',
                    'capabilities' => ['read', 'update', 'delete'],
                ],
            ],
        ];

        $userPolicy = [
            'name' => 'user-policy',
            'description' => 'User access',
            'rules' => [
                [
                    'path' => '/api/users/${id}',
                    'effect' => 'allow',
                    'capabilities' => ['read', 'update'],
                    'conditions' => ['userId' => '${id}'],
                ],
            ],
        ];

        file_put_contents($this->tempDir.'/admin.json', json_encode($adminPolicy));
        file_put_contents($this->tempDir.'/user.json', json_encode($userPolicy));

        $repository = new JsonRepository($this->tempDir, true);

        expect($repository->all())->toHaveCount(2);

        $admin = $repository->get('admin-policy');
        $user = $repository->get('user-policy');

        expect($admin->getRules())->toHaveCount(1)
            ->and($user->getRules())->toHaveCount(1)
            ->and($user->getRules()[0]->getConditions())->toBe(['userId' => '${id}']);
    });
});

describe('JsonRepository get() method', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/arbiter-json-tests-'.uniqid();
        mkdir($this->tempDir);

        $data = [
            [
                'name' => 'policy-1',
                'description' => 'First policy',
                'rules' => [],
            ],
            [
                'name' => 'policy-2',
                'description' => 'Second policy',
                'rules' => [],
            ],
        ];
        file_put_contents($this->tempDir.'/policies.json', json_encode($data));

        $this->repository = new JsonRepository($this->tempDir.'/policies.json');
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

describe('JsonRepository has() method', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/arbiter-json-tests-'.uniqid();
        mkdir($this->tempDir);

        $data = [
            'name' => 'test-policy',
            'description' => 'Test',
            'rules' => [],
        ];
        file_put_contents($this->tempDir.'/policy.json', json_encode($data));

        $this->repository = new JsonRepository($this->tempDir.'/policy.json');
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

describe('JsonRepository all() method', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/arbiter-json-tests-'.uniqid();
        mkdir($this->tempDir);

        $data = [
            [
                'name' => 'admin-policy',
                'description' => 'Admin',
                'rules' => [],
            ],
            [
                'name' => 'user-policy',
                'description' => 'User',
                'rules' => [],
            ],
        ];
        file_put_contents($this->tempDir.'/policies.json', json_encode($data));

        $this->repository = new JsonRepository($this->tempDir.'/policies.json');
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

describe('JsonRepository getMany() method', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/arbiter-json-tests-'.uniqid();
        mkdir($this->tempDir);

        $data = [
            [
                'name' => 'policy-1',
                'description' => 'First',
                'rules' => [],
            ],
            [
                'name' => 'policy-2',
                'description' => 'Second',
                'rules' => [],
            ],
            [
                'name' => 'policy-3',
                'description' => 'Third',
                'rules' => [],
            ],
        ];
        file_put_contents($this->tempDir.'/policies.json', json_encode($data));

        $this->repository = new JsonRepository($this->tempDir.'/policies.json');
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
            ->toThrow(MultiplePoliciesNotFoundException::class, 'Policies not found: nonexistent');
    });

    test('getMany() throws exception listing all missing policies', function (): void {
        expect(fn () => $this->repository->getMany(['missing-1', 'missing-2', 'missing-3']))
            ->toThrow(MultiplePoliciesNotFoundException::class, 'Policies not found: missing-1, missing-2, missing-3');
    });
});

describe('JsonRepository edge cases', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/arbiter-json-tests-'.uniqid();
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

    test('repository handles empty JSON array', function (): void {
        $jsonPath = $this->tempDir.'/empty.json';
        file_put_contents($jsonPath, json_encode([]));

        $repository = new JsonRepository($jsonPath);

        expect($repository->all())->toBe([]);
    });

    test('repository handles pretty-printed JSON', function (): void {
        $jsonPath = $this->tempDir.'/pretty.json';
        $data = [
            'name' => 'test-policy',
            'description' => 'Test',
            'rules' => [],
        ];
        file_put_contents($jsonPath, json_encode($data, \JSON_PRETTY_PRINT));

        $repository = new JsonRepository($jsonPath);

        expect($repository->has('test-policy'))->toBeTrue();
    });

    test('repository handles JSON with UTF-8 characters', function (): void {
        $jsonPath = $this->tempDir.'/utf8.json';
        $data = [
            'name' => 'international-policy',
            'description' => 'Politique d\'accès avec caractères spéciaux: é, ñ, 中文',
            'rules' => [],
        ];
        file_put_contents($jsonPath, json_encode($data, \JSON_UNESCAPED_UNICODE));

        $repository = new JsonRepository($jsonPath);

        $policy = $repository->get('international-policy');
        expect($policy->getDescription())->toContain('é')
            ->and($policy->getDescription())->toContain('中文');
    });

    test('repository in directory mode handles many files', function (): void {
        // Create 10 policy files
        for ($i = 1; $i <= 10; ++$i) {
            $data = [
                'name' => 'policy-'.$i,
                'description' => 'Policy number '.$i,
                'rules' => [],
            ];
            file_put_contents($this->tempDir.sprintf('/policy-%d.json', $i), json_encode($data));
        }

        $repository = new JsonRepository($this->tempDir, true);

        expect($repository->all())->toHaveCount(10);

        // Verify each policy is accessible
        for ($i = 1; $i <= 10; ++$i) {
            expect($repository->has('policy-'.$i))->toBeTrue();
        }
    });
});
