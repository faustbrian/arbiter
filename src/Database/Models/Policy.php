<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Arbiter\Database\Models;

use Cline\Arbiter\Policy as PolicyValue;
use Cline\VariableKeys\Database\Concerns\HasVariablePrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

use function config;
use function json_encode;

/**
 * Eloquent model for storing policy definitions in the database.
 *
 * Stores access control policies with their rules and metadata.
 * Each policy contains a set of rules that determine access permissions
 * for paths, resources, or actions.
 *
 * The model automatically serializes Policy value objects to JSON for storage
 * and deserializes them back when retrieved.
 *
 * @property Carbon                    $created_at
 * @property null|string               $description Policy description
 * @property int|string                $id          Primary key (type depends on configuration)
 * @property bool                      $is_active   Whether this policy is currently active
 * @property null|array<string, mixed> $metadata    Additional policy metadata
 * @property string                    $name        Unique policy name
 * @property array<string, mixed>      $rules       Policy rules as JSON
 * @property Carbon                    $updated_at
 *
 * @use HasFactory<Factory<static>>
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Policy extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasVariablePrimaryKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'rules',
        'metadata',
        'is_active',
    ];

    /**
     * Create an Eloquent model from a Policy value object.
     *
     * @param PolicyValue $policy The policy value object
     *
     * @return static New model instance
     */
    public static function fromPolicy(PolicyValue $policy): static
    {
        $data = $policy->jsonSerialize();

        return new self([
            'name' => $data['name'],
            'description' => empty($data['description']) ? null : $data['description'],
            'rules' => $data['rules'],
            'is_active' => true,
        ]);
    }

    /**
     * Find a policy by name.
     *
     * @param string $name The policy name
     *
     * @return null|static The policy model or null
     */
    public static function findByName(string $name): ?static
    {
        return self::query()->where('name', $name)->first();
    }

    /**
     * Get the table associated with the model.
     */
    #[Override()]
    public function getTable(): string
    {
        /** @var null|string */
        $configured = config('arbiter.tables.policies');

        if ($configured !== null) {
            return $configured;
        }

        $parent = parent::getTable();

        return $parent ?: 'policies';
    }

    /**
     * Convert this Eloquent model to a Policy value object.
     *
     * @return PolicyValue The immutable policy value object
     */
    public function toPolicy(): PolicyValue
    {
        return PolicyValue::fromArray([
            'name' => $this->name,
            'description' => $this->description ?? '',
            'rules' => $this->rules ?? [],
        ]);
    }

    /**
     * Set the rules attribute, defaulting to empty array if null.
     *
     * @param null|array<string, mixed> $value
     */
    protected function setRulesAttribute(?array $value): void
    {
        $this->attributes['rules'] = json_encode($value ?? []);
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Scope a query to only include active policies.
     *
     * @param  Builder<static> $query
     * @return Builder<static>
     */
    #[Scope()]
    protected function active($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive policies.
     *
     * @param  Builder<static> $query
     * @return Builder<static>
     */
    #[Scope()]
    protected function inactive($query)
    {
        return $query->where('is_active', false);
    }
}
