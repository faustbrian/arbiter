<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Database\Concerns;

use Illuminate\Support\Str;

use function config;
use function is_string;

/**
 * Dynamically apply the configured primary key strategy to Eloquent models.
 *
 * Based on the arbiter.primary_key_type configuration, this trait will:
 * - Use auto-increment IDs (default Laravel behavior)
 * - Use UUIDs
 * - Use ULIDs
 *
 * This allows all Arbiter models to respect a single configuration setting
 * for primary key strategy across the entire package.
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait HasArbiterPrimaryKey
{
    /**
     * Initialize the trait and apply the configured primary key strategy.
     *
     * This method is automatically called by Laravel when the model is booted.
     */
    public function initializeHasArbiterPrimaryKey(): void
    {
        // Set up creating event to generate UUID/ULID if needed
        if (config('arbiter.primary_key_type') !== 'uuid' && config('arbiter.primary_key_type') !== 'ulid') {
            return;
        }

        static::creating(function (self $model): void {
            if ($model->getKey()) {
                return;
            }

            $model->{$model->getKeyName()} = $model->newUniqueId();
        });
    }

    /**
     * Generate a new unique ID for the model.
     */
    public function newUniqueId(): string
    {
        return match (config('arbiter.primary_key_type', 'id')) {
            'uuid' => (string) Str::uuid(),
            'ulid' => (string) Str::ulid(),
            default => '',
        };
    }

    /**
     * Determine if the given value is a valid unique ID.
     */
    public function isValidUniqueId(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return match (config('arbiter.primary_key_type', 'id')) {
            'uuid' => Str::isUuid($value),
            'ulid' => Str::isUlid($value),
            default => false,
        };
    }

    /**
     * Get the primary key type for the model.
     *
     * @return string The primary key column name
     */
    public function getKeyName(): string
    {
        return match (config('arbiter.primary_key_type', 'id')) {
            'uuid', 'ulid' => 'id',
            default => parent::getKeyName(),
        };
    }

    /**
     * Get the auto-incrementing key type.
     *
     * @return string The key type (int or string)
     */
    public function getKeyType(): string
    {
        return match (config('arbiter.primary_key_type', 'id')) {
            'uuid', 'ulid' => 'string',
            default => parent::getKeyType(),
        };
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool Whether IDs auto-increment
     */
    public function getIncrementing(): bool
    {
        return match (config('arbiter.primary_key_type', 'id')) {
            'uuid', 'ulid' => false,
            default => parent::getIncrementing(),
        };
    }
}
