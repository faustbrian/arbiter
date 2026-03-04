<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Repository\ArrayRepository;
use Cline\Arbiter\Repository\CachedRepository;
use Cline\Arbiter\Rule;
use Psr\SimpleCache\CacheInterface;

describe('CachedRepository', function (): void {
    describe('get() with cache hit/miss', function (): void {
        test('returns cached policy when cache hit occurs', function (): void {
            // Arrange
            $policy = Policy::create('test-policy')
                ->description('Test policy description');

            $cache = new class() implements CacheInterface
            {
                private array $storage = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->storage[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->storage[$key] = $value;

                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->storage[$key]);

                    return true;
                }

                public function clear(): bool
                {
                    $this->storage = [];

                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return isset($this->storage[$key]);
                }
            };

            // Pre-populate cache
            $cache->set('arbiter:policies:test-policy', $policy);

            $inner = new ArrayRepository([]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->get('test-policy');

            // Assert
            expect($result)->toBe($policy)
                ->and($result->getName())->toBe('test-policy')
                ->and($result->getDescription())->toBe('Test policy description');
        });

        test('fetches from inner repository and caches on cache miss', function (): void {
            // Arrange
            $policy = Policy::create('test-policy')
                ->description('Test policy description');

            $cache = new class() implements CacheInterface
            {
                public int $setCalls = 0;

                private array $storage = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->storage[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->storage[$key] = $value;
                    ++$this->setCalls;

                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->storage[$key]);

                    return true;
                }

                public function clear(): bool
                {
                    $this->storage = [];

                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return isset($this->storage[$key]);
                }
            };

            $inner = new ArrayRepository([$policy]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->get('test-policy');

            // Assert
            expect($result)->toBe($policy)
                ->and($cache->setCalls)->toBe(1)
                ->and($cache->get('arbiter:policies:test-policy'))->toBe($policy);
        });

        test('uses custom cache prefix', function (): void {
            // Arrange
            $policy = Policy::create('test-policy');

            $cache = new class() implements CacheInterface
            {
                public ?string $lastKey = null;

                private array $storage = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    $this->lastKey = $key;

                    return $this->storage[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->storage[$key] = $value;
                    $this->lastKey = $key;

                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->storage[$key]);

                    return true;
                }

                public function clear(): bool
                {
                    $this->storage = [];

                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return isset($this->storage[$key]);
                }
            };

            $inner = new ArrayRepository([$policy]);
            $repository = new CachedRepository($inner, $cache, null, 'custom:prefix:');

            // Act
            $repository->get('test-policy');

            // Assert
            expect($cache->lastKey)->toBe('custom:prefix:test-policy');
        });

        test('respects TTL when caching', function (): void {
            // Arrange
            $policy = Policy::create('test-policy');

            $cache = new class() implements CacheInterface
            {
                public ?int $lastTtl = null;

                public function get(string $key, mixed $default = null): mixed
                {
                    return $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->lastTtl = $ttl;

                    return true;
                }

                public function delete(string $key): bool
                {
                    return true;
                }

                public function clear(): bool
                {
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return false;
                }
            };

            $inner = new ArrayRepository([$policy]);
            $repository = new CachedRepository($inner, $cache, 3_600);

            // Act
            $repository->get('test-policy');

            // Assert
            expect($cache->lastTtl)->toBe(3_600);
        });

        test('throws PolicyNotFoundException when policy not found', function (): void {
            // Arrange
            $cache = new class() implements CacheInterface
            {
                public function get(string $key, mixed $default = null): mixed
                {
                    return $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function delete(string $key): bool
                {
                    return true;
                }

                public function clear(): bool
                {
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return false;
                }
            };

            $inner = new ArrayRepository([]);
            $repository = new CachedRepository($inner, $cache);

            // Act & Assert
            expect(fn (): Policy => $repository->get('nonexistent'))
                ->toThrow(PolicyNotFoundException::class);
        });
    });

    describe('has() with cache check', function (): void {
        test('returns true when policy exists in cache', function (): void {
            // Arrange
            $policy = Policy::create('cached-policy');

            $cache = new class() implements CacheInterface
            {
                private array $storage = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->storage[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->storage[$key] = $value;

                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->storage[$key]);

                    return true;
                }

                public function clear(): bool
                {
                    $this->storage = [];

                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return isset($this->storage[$key]);
                }
            };

            // Pre-populate cache
            $cache->set('arbiter:policies:cached-policy', $policy);

            $inner = new ArrayRepository([]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->has('cached-policy');

            // Assert
            expect($result)->toBeTrue();
        });

        test('checks inner repository when not in cache', function (): void {
            // Arrange
            $policy = Policy::create('inner-policy');

            $cache = new class() implements CacheInterface
            {
                public function get(string $key, mixed $default = null): mixed
                {
                    return $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function delete(string $key): bool
                {
                    return true;
                }

                public function clear(): bool
                {
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return false;
                }
            };

            $inner = new ArrayRepository([$policy]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->has('inner-policy');

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns false when policy not found anywhere', function (): void {
            // Arrange
            $cache = new class() implements CacheInterface
            {
                public function get(string $key, mixed $default = null): mixed
                {
                    return $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function delete(string $key): bool
                {
                    return true;
                }

                public function clear(): bool
                {
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return false;
                }
            };

            $inner = new ArrayRepository([]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->has('nonexistent');

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('all() bypasses cache', function (): void {
        test('always fetches from inner repository', function (): void {
            // Arrange
            $policy1 = Policy::create('policy-1');
            $policy2 = Policy::create('policy-2');

            $cache = new class() implements CacheInterface
            {
                public int $getCalls = 0;

                public function get(string $key, mixed $default = null): mixed
                {
                    ++$this->getCalls;

                    return $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function delete(string $key): bool
                {
                    return true;
                }

                public function clear(): bool
                {
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return false;
                }
            };

            $inner = new ArrayRepository([$policy1, $policy2]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->all();

            // Assert
            expect($result)->toHaveCount(2)
                ->and($result['policy-1'])->toBe($policy1)
                ->and($result['policy-2'])->toBe($policy2)
                ->and($cache->getCalls)->toBe(0);
        });

        test('returns empty array when no policies exist', function (): void {
            // Arrange
            $cache = new class() implements CacheInterface
            {
                public function get(string $key, mixed $default = null): mixed
                {
                    return $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function delete(string $key): bool
                {
                    return true;
                }

                public function clear(): bool
                {
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return false;
                }
            };

            $inner = new ArrayRepository([]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->all();

            // Assert
            expect($result)->toBeArray()
                ->and($result)->toBeEmpty();
        });
    });

    describe('getMany() with partial cache', function (): void {
        test('fetches all policies from cache when available', function (): void {
            // Arrange
            $policy1 = Policy::create('policy-1');
            $policy2 = Policy::create('policy-2');

            $cache = new class() implements CacheInterface
            {
                private array $storage = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->storage[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->storage[$key] = $value;

                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->storage[$key]);

                    return true;
                }

                public function clear(): bool
                {
                    $this->storage = [];

                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return isset($this->storage[$key]);
                }
            };

            // Pre-populate cache
            $cache->set('arbiter:policies:policy-1', $policy1);
            $cache->set('arbiter:policies:policy-2', $policy2);

            $inner = new ArrayRepository([]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->getMany(['policy-1', 'policy-2']);

            // Assert
            expect($result)->toHaveCount(2)
                ->and($result['policy-1'])->toBe($policy1)
                ->and($result['policy-2'])->toBe($policy2);
        });

        test('fetches uncached policies from inner repository', function (): void {
            // Arrange
            $cachedPolicy = Policy::create('cached-policy');
            $uncachedPolicy = Policy::create('uncached-policy');

            $cache = new class() implements CacheInterface
            {
                public int $setCalls = 0;

                private array $storage = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->storage[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->storage[$key] = $value;
                    ++$this->setCalls;

                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->storage[$key]);

                    return true;
                }

                public function clear(): bool
                {
                    $this->storage = [];

                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return isset($this->storage[$key]);
                }
            };

            // Pre-populate cache with one policy
            $cache->set('arbiter:policies:cached-policy', $cachedPolicy);
            $cache->setCalls = 0; // Reset counter after pre-population

            $inner = new ArrayRepository([$cachedPolicy, $uncachedPolicy]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->getMany(['cached-policy', 'uncached-policy']);

            // Assert
            expect($result)->toHaveCount(2)
                ->and($result['cached-policy'])->toBe($cachedPolicy)
                ->and($result['uncached-policy'])->toBe($uncachedPolicy)
                ->and($cache->setCalls)->toBe(1); // Only uncached policy should be cached
        });

        test('caches fetched policies for future use', function (): void {
            // Arrange
            $policy1 = Policy::create('policy-1');
            $policy2 = Policy::create('policy-2');

            $cache = new class() implements CacheInterface
            {
                private array $storage = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->storage[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->storage[$key] = $value;

                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->storage[$key]);

                    return true;
                }

                public function clear(): bool
                {
                    $this->storage = [];

                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return isset($this->storage[$key]);
                }
            };

            $inner = new ArrayRepository([$policy1, $policy2]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $repository->getMany(['policy-1', 'policy-2']);

            // Assert
            expect($cache->get('arbiter:policies:policy-1'))->toBe($policy1)
                ->and($cache->get('arbiter:policies:policy-2'))->toBe($policy2);
        });

        test('returns empty array when no policies requested', function (): void {
            // Arrange
            $cache = new class() implements CacheInterface
            {
                public function get(string $key, mixed $default = null): mixed
                {
                    return $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function delete(string $key): bool
                {
                    return true;
                }

                public function clear(): bool
                {
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return false;
                }
            };

            $inner = new ArrayRepository([]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->getMany([]);

            // Assert
            expect($result)->toBeArray()
                ->and($result)->toBeEmpty();
        });

        test('respects TTL when caching fetched policies', function (): void {
            // Arrange
            $policy = Policy::create('test-policy');

            $cache = new class() implements CacheInterface
            {
                public ?int $lastTtl = null;

                public function get(string $key, mixed $default = null): mixed
                {
                    return $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->lastTtl = $ttl;

                    return true;
                }

                public function delete(string $key): bool
                {
                    return true;
                }

                public function clear(): bool
                {
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return false;
                }
            };

            $inner = new ArrayRepository([$policy]);
            $repository = new CachedRepository($inner, $cache, 7_200);

            // Act
            $repository->getMany(['test-policy']);

            // Assert
            expect($cache->lastTtl)->toBe(7_200);
        });
    });

    describe('forget() invalidation', function (): void {
        test('removes policy from cache', function (): void {
            // Arrange
            $policy = Policy::create('test-policy');

            $cache = new class() implements CacheInterface
            {
                private array $storage = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->storage[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->storage[$key] = $value;

                    return true;
                }

                public function delete(string $key): bool
                {
                    if (isset($this->storage[$key])) {
                        unset($this->storage[$key]);

                        return true;
                    }

                    return false;
                }

                public function clear(): bool
                {
                    $this->storage = [];

                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return isset($this->storage[$key]);
                }
            };

            // Pre-populate cache
            $cache->set('arbiter:policies:test-policy', $policy);

            $inner = new ArrayRepository([]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->forget('test-policy');

            // Assert
            expect($result)->toBeTrue()
                ->and($cache->has('arbiter:policies:test-policy'))->toBeFalse();
        });

        test('returns false when policy not in cache', function (): void {
            // Arrange
            $cache = new class() implements CacheInterface
            {
                public function get(string $key, mixed $default = null): mixed
                {
                    return $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function delete(string $key): bool
                {
                    return false;
                }

                public function clear(): bool
                {
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return false;
                }
            };

            $inner = new ArrayRepository([]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->forget('nonexistent');

            // Assert
            expect($result)->toBeFalse();
        });

        test('next get() fetches fresh data after forget()', function (): void {
            // Arrange
            $oldPolicy = Policy::create('test-policy')->description('Old description');
            $newPolicy = Policy::create('test-policy')->description('New description');

            $cache = new class() implements CacheInterface
            {
                private array $storage = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->storage[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->storage[$key] = $value;

                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->storage[$key]);

                    return true;
                }

                public function clear(): bool
                {
                    $this->storage = [];

                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return isset($this->storage[$key]);
                }
            };

            // Pre-populate cache with old policy
            $cache->set('arbiter:policies:test-policy', $oldPolicy);

            // Update inner repository with new policy
            $inner = new ArrayRepository([$newPolicy]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $repository->forget('test-policy');

            $result = $repository->get('test-policy');

            // Assert
            expect($result->getDescription())->toBe('New description');
        });

        test('uses correct cache key with custom prefix', function (): void {
            // Arrange
            $cache = new class() implements CacheInterface
            {
                public ?string $lastDeletedKey = null;

                public function get(string $key, mixed $default = null): mixed
                {
                    return $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function delete(string $key): bool
                {
                    $this->lastDeletedKey = $key;

                    return true;
                }

                public function clear(): bool
                {
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return false;
                }
            };

            $inner = new ArrayRepository([]);
            $repository = new CachedRepository($inner, $cache, null, 'custom:');

            // Act
            $repository->forget('test-policy');

            // Assert
            expect($cache->lastDeletedKey)->toBe('custom:test-policy');
        });
    });

    describe('flush() clearing cache', function (): void {
        test('clears all cached policies', function (): void {
            // Arrange
            $policy1 = Policy::create('policy-1');
            $policy2 = Policy::create('policy-2');

            $cache = new class() implements CacheInterface
            {
                private array $storage = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->storage[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->storage[$key] = $value;

                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->storage[$key]);

                    return true;
                }

                public function clear(): bool
                {
                    $this->storage = [];

                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return isset($this->storage[$key]);
                }
            };

            // Pre-populate cache
            $cache->set('arbiter:policies:policy-1', $policy1);
            $cache->set('arbiter:policies:policy-2', $policy2);

            $inner = new ArrayRepository([]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->flush();

            // Assert
            expect($result)->toBeTrue()
                ->and($cache->has('arbiter:policies:policy-1'))->toBeFalse()
                ->and($cache->has('arbiter:policies:policy-2'))->toBeFalse();
        });

        test('returns true on successful flush', function (): void {
            // Arrange
            $cache = new class() implements CacheInterface
            {
                public function get(string $key, mixed $default = null): mixed
                {
                    return $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function delete(string $key): bool
                {
                    return true;
                }

                public function clear(): bool
                {
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return false;
                }
            };

            $inner = new ArrayRepository([]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->flush();

            // Assert
            expect($result)->toBeTrue();
        });

        test('clears entire cache pool per PSR-16 limitation', function (): void {
            // Arrange
            $cache = new class() implements CacheInterface
            {
                public int $clearCalls = 0;

                private array $storage = [
                    'arbiter:policies:policy-1' => 'value1',
                    'other:data:key' => 'value2',
                ];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->storage[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->storage[$key] = $value;

                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->storage[$key]);

                    return true;
                }

                public function clear(): bool
                {
                    $this->storage = [];
                    ++$this->clearCalls;

                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return isset($this->storage[$key]);
                }
            };

            $inner = new ArrayRepository([]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $repository->flush();

            // Assert
            expect($cache->clearCalls)->toBe(1)
                ->and($cache->has('arbiter:policies:policy-1'))->toBeFalse()
                ->and($cache->has('other:data:key'))->toBeFalse();
        });
    });

    describe('Edge cases and integration', function (): void {
        test('handles policy with rules correctly', function (): void {
            // Arrange
            $policy = Policy::create('admin-policy')
                ->description('Admin access policy')
                ->addRule(Rule::allow('/admin/**'));

            $cache = new class() implements CacheInterface
            {
                private array $storage = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->storage[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->storage[$key] = $value;

                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->storage[$key]);

                    return true;
                }

                public function clear(): bool
                {
                    $this->storage = [];

                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return isset($this->storage[$key]);
                }
            };

            $inner = new ArrayRepository([$policy]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $result = $repository->get('admin-policy');

            // Assert
            expect($result->getName())->toBe('admin-policy')
                ->and($result->getDescription())->toBe('Admin access policy')
                ->and($result->getRules())->toHaveCount(1);
        });

        test('null TTL means cache forever', function (): void {
            // Arrange
            $policy = Policy::create('test-policy');

            $cache = new class() implements CacheInterface
            {
                public ?int $lastTtl = 999;

                public function get(string $key, mixed $default = null): mixed
                {
                    return $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->lastTtl = $ttl;

                    return true;
                }

                public function delete(string $key): bool
                {
                    return true;
                }

                public function clear(): bool
                {
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return false;
                }
            };

            $inner = new ArrayRepository([$policy]);
            $repository = new CachedRepository($inner, $cache);

            // Act
            $repository->get('test-policy');

            // Assert
            expect($cache->lastTtl)->toBeNull();
        });

        test('readonly immutability is enforced', function (): void {
            // Arrange
            $cache = new class() implements CacheInterface
            {
                public function get(string $key, mixed $default = null): mixed
                {
                    return $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function delete(string $key): bool
                {
                    return true;
                }

                public function clear(): bool
                {
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return false;
                }
            };

            $inner = new ArrayRepository([]);
            $repository = new CachedRepository($inner, $cache);

            // Act & Assert
            expect($repository)->toBeInstanceOf(CachedRepository::class);

            // Verify class is readonly (this is a compile-time check, but we can verify the instance)
            $reflection = new ReflectionClass($repository);
            expect($reflection->isReadOnly())->toBeTrue();
        });
    });
});
