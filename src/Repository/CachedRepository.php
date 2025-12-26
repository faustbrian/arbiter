<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Repository;

use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;
use Psr\SimpleCache\CacheInterface;

/**
 * Wrap any repository with PSR-16 caching.
 *
 * Caches individual policy definitions to reduce database queries or
 * file I/O operations. Provides methods to invalidate cached entries.
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class CachedRepository implements PolicyRepositoryInterface
{
    /**
     * @param PolicyRepositoryInterface $inner  The repository to wrap with caching
     * @param CacheInterface            $cache  PSR-16 cache implementation
     * @param null|int                  $ttl    Cache time-to-live in seconds (null = forever)
     * @param string                    $prefix Cache key prefix to avoid collisions
     */
    public function __construct(
        private PolicyRepositoryInterface $inner,
        private CacheInterface $cache,
        private ?int $ttl = null,
        private string $prefix = 'arbiter:policies:',
    ) {}

    /**
     * Get a policy by name.
     *
     * Returns cached policy if available, otherwise fetches from inner repository
     * and caches the result.
     *
     * @param  string                  $name The policy name
     * @throws PolicyNotFoundException If the policy is not found
     * @return Policy                  The policy instance
     */
    public function get(string $name): Policy
    {
        $cacheKey = $this->getCacheKey($name);

        // Try to get from cache first
        $cached = $this->cache->get($cacheKey);

        if ($cached instanceof Policy) {
            return $cached;
        }

        // Fetch from inner repository
        $policy = $this->inner->get($name);

        // Store in cache
        $this->cache->set($cacheKey, $policy, $this->ttl);

        return $policy;
    }

    /**
     * Check if a policy exists.
     *
     * Checks cache first, then falls back to inner repository.
     *
     * @param  string $name The policy name
     * @return bool   True if the policy exists, false otherwise
     */
    public function has(string $name): bool
    {
        $cacheKey = $this->getCacheKey($name);

        // If it's in cache, it exists
        if ($this->cache->has($cacheKey)) {
            return true;
        }

        // Check inner repository
        return $this->inner->has($name);
    }

    /**
     * Get all policies.
     *
     * This method bypasses the cache and always fetches from the inner repository,
     * as caching all policies would be inefficient.
     *
     * @return array<string, Policy> Map of policy names to instances
     */
    public function all(): array
    {
        // Don't cache all() results as it's typically only called once at initialization
        return $this->inner->all();
    }

    /**
     * Get multiple policies.
     *
     * Fetches from cache where possible, then fetches remaining from inner repository.
     *
     * @param  array<string>         $names The policy names to retrieve
     * @return array<string, Policy> Map of policy names to instances
     */
    public function getMany(array $names): array
    {
        /** @var array<string, Policy> */
        $result = [];
        $uncached = [];

        // Try to get each from cache
        foreach ($names as $name) {
            $cacheKey = $this->getCacheKey($name);
            $cached = $this->cache->get($cacheKey);

            if ($cached instanceof Policy) {
                $result[$name] = $cached;
            } else {
                $uncached[] = $name;
            }
        }

        // Fetch uncached policies from inner repository
        if ($uncached !== []) {
            $fetched = $this->inner->getMany($uncached);

            // Store fetched policies in cache
            foreach ($fetched as $name => $policy) {
                $cacheKey = $this->getCacheKey($name);
                $this->cache->set($cacheKey, $policy, $this->ttl);
                $result[$name] = $policy;
            }
        }

        return $result;
    }

    /**
     * Invalidate a cached policy.
     *
     * Removes the cached policy for the specified name.
     * The next get() call will fetch fresh data from the inner repository.
     *
     * @param  string $name The policy name to invalidate
     * @return bool   True if the cached entry was deleted, false if it didn't exist
     */
    public function forget(string $name): bool
    {
        $cacheKey = $this->getCacheKey($name);

        return $this->cache->delete($cacheKey);
    }

    /**
     * Clear all cached policies.
     *
     * Removes all cached entries with this repository's prefix.
     * Note: This uses the clear() method which clears the entire cache pool.
     * If you share the cache with other data, consider using a dedicated cache instance.
     *
     * @return bool True if the cache was successfully cleared
     */
    public function flush(): bool
    {
        // Note: PSR-16 doesn't support clearing by prefix, so this clears the entire cache
        // In production, use a dedicated cache pool for policy definitions
        return $this->cache->clear();
    }

    /**
     * Generate a cache key for a policy name.
     *
     * @param  string $name The policy name
     * @return string The cache key
     */
    private function getCacheKey(string $name): string
    {
        return $this->prefix.$name;
    }
}
