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
 * @method static array<string, Policy>     all()
 * @method static PolicyEvaluationConductor for(string|array<string|Policy>|Policy $policy)
 * @method static Policy                    get(string $name)
 * @method static bool                      has(string $name)
 * @method static PathEvaluationConductor   path(string $path)
 * @method static void                      register(Policy $policy)
 * @method static ArbiterManager            repository(PolicyRepositoryInterface $repository)
 *
 * @author Brian Faust <brian@cline.sh>
 * @see ArbiterManager
 */
final class Arbiter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'arbiter';
    }
}
