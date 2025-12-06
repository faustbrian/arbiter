<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Repository;

use Cline\Arbiter\Exception\MultiplePoliciesNotFoundException;
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;

use function implode;
use function throw_unless;

/**
 * In-memory policy repository backed by an array.
 *
 * Useful for testing or when policies are defined programmatically.
 * @author Brian Faust <brian@cline.sh>
 */
final class ArrayRepository implements PolicyRepositoryInterface
{
    /** @var array<string, Policy> */
    private array $policies = [];

    /**
     * @param array<Policy> $policies
     */
    public function __construct(array $policies = [])
    {
        foreach ($policies as $policy) {
            $this->policies[$policy->getName()] = $policy;
        }
    }

    public function get(string $name): Policy
    {
        throw_unless($this->has($name), PolicyNotFoundException::forName($name));

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
        $missing = [];
        $policies = [];

        foreach ($names as $name) {
            if ($this->has($name)) {
                $policies[$name] = $this->policies[$name];
            } else {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            $nameList = implode(', ', $missing);

            throw MultiplePoliciesNotFoundException::forNames($nameList);
        }

        return $policies;
    }
}
