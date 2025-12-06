<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter;

use Cline\Arbiter\Services\EvaluationService;
use Cline\Arbiter\Services\PolicyRegistry;
use Cline\Arbiter\Services\SpecificityCalculator;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ArbiterServiceProvider extends ServiceProvider
{
    #[Override()]
    public function register(): void
    {
        $this->app->singleton(PolicyRegistry::class);

        $this->app->singleton(SpecificityCalculator::class);

        $this->app->singleton(function ($app): EvaluationService {
            /** @var SpecificityCalculator $specificityCalculator */
            $specificityCalculator = $app->make(SpecificityCalculator::class); // @phpstan-ignore method.nonObject

            return new EvaluationService(
                specificityCalculator: $specificityCalculator,
            );
        });

        $this->app->singleton('arbiter', function ($app): ArbiterManager {
            /** @var PolicyRegistry $registry */
            $registry = $app->make(PolicyRegistry::class); // @phpstan-ignore method.nonObject

            /** @var EvaluationService $evaluator */
            $evaluator = $app->make(EvaluationService::class); // @phpstan-ignore method.nonObject

            return new ArbiterManager(
                registry: $registry,
                evaluator: $evaluator,
            );
        });

        $this->app->alias('arbiter', ArbiterManager::class);
    }

    /**
     * @return array<int, class-string|string>
     */
    #[Override()]
    public function provides(): array
    {
        return [
            'arbiter',
            ArbiterManager::class,
            PolicyRegistry::class,
            EvaluationService::class,
            SpecificityCalculator::class,
        ];
    }
}
