<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter;

use Cline\Arbiter\Database\ModelRegistry;
use Cline\Arbiter\Services\EvaluationService;
use Cline\Arbiter\Services\PolicyRegistry;
use Cline\Arbiter\Services\SpecificityCalculator;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Laravel service provider for Arbiter policy evaluation system.
 *
 * Registers all core services as singletons in the container, including
 * the main ArbiterManager facade, policy registry, evaluation services,
 * and specificity calculation components.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ArbiterServiceProvider extends ServiceProvider
{
    /**
     * Register Arbiter services in the container.
     *
     * Binds all core components as singletons to ensure consistent state
     * across the application. The 'arbiter' alias provides convenient
     * access to the main ArbiterManager instance.
     */
    #[Override()]
    public function register(): void
    {
        $this->app->singleton(ModelRegistry::class);

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
     * Get the services provided by the provider.
     *
     * Returns array of service identifiers that this provider makes available.
     * Used by Laravel's deferred service provider system to optimize loading.
     *
     * @return array<int, class-string|string> Array of provided service class names and aliases
     */
    #[Override()]
    public function provides(): array
    {
        return [
            'arbiter',
            ArbiterManager::class,
            ModelRegistry::class,
            PolicyRegistry::class,
            EvaluationService::class,
            SpecificityCalculator::class,
        ];
    }
}
