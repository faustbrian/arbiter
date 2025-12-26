<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Arbiter\ArbiterServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Override;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param  Application              $app
     * @return array<int, class-string>
     */
    #[Override()]
    protected function getPackageProviders($app): array
    {
        return [
            ArbiterServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param Application $app
     */
    #[Override()]
    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite in memory
        $app->make(Repository::class)->set('database.default', 'testbench');
        $app->make(Repository::class)->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure Arbiter
        $app->make(Repository::class)->set('arbiter.primary_key_type', 'id');
        $app->make(Repository::class)->set('arbiter.use_database', true);
        $app->make(Repository::class)->set('arbiter.tables.policies', 'policies');
    }

    /**
     * Define database migrations.
     */
    #[Override()]
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
