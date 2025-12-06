<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Facades;

use Cline\Arbiter\ArbiterManager;
use Cline\Arbiter\Conductors\PathEvaluationConductor;
use Cline\Arbiter\Conductors\PolicyEvaluationConductor;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Repository\PolicyRepositoryInterface;
use Illuminate\Support\Facades\Facade;

/**
 * Laravel facade for the Arbiter access control manager.
 *
 * Provides static access to policy registration, retrieval, and evaluation
 * through Laravel's service container. The facade proxies method calls to
 * the underlying ArbiterManager instance.
 *
 * @method static array<string, Policy>     all()                                             Retrieve all registered policies
 * @method static PolicyEvaluationConductor for(string|array<string|Policy>|Policy $policy)   Create evaluation conductor for specified policies
 * @method static Policy                    get(string $name)                                 Retrieve a policy by name
 * @method static bool                      has(string $name)                                 Check if a policy exists
 * @method static PathEvaluationConductor   path(string $path)                                Create path-based evaluation conductor
 * @method static void                      register(Policy $policy)                          Register a policy in the manager
 * @method static ArbiterManager            repository(PolicyRepositoryInterface $repository) Set the policy repository
 *
 * @author Brian Faust <brian@cline.sh>
 * @see ArbiterManager
 */
final class Arbiter extends Facade
{
    /**
     * Get the registered name of the component in the service container.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'arbiter';
    }
}
