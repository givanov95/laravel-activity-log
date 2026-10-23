<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Models;

use BackedEnum;
use DateTimeInterface;
use Givanov95\LaravelActivityLog\Exceptions\ImmutableActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One logged action. Append-only: updating or deleting through the model
 * throws; maintenance goes through ActivityLog::scrub(), forgetScope() and
 * activity-log:prune.
 *
 * @property int                       $id
 * @property string                    $action
 * @property string|null               $batch_uuid
 * @property string|null               $scope_type
 * @property int|string|null           $scope_id
 * @property string|null               $subject_type
 * @property int|string|null           $subject_id
 * @property string|null               $subject_label
 * @property string|null               $actor_type
 * @property int|string|null           $actor_id
 * @property string|null               $actor_role
 * @property string|null               $actor_label
 * @property array<string, mixed>|null $changes
 * @property array<string, mixed>|null $properties
 * @property string|null               $ip_address
 * @property \Illuminate\Support\Carbon $created_at
 */
class ActivityLogEntry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected $casts = [
        'changes'    => 'array',
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('activity-log.table', 'activity_logs');
    }

    public function getConnectionName(): ?string
    {
        return config('activity-log.connection') ?? parent::getConnectionName();
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw ImmutableActivityLog::make('update'));
        static::deleting(fn () => throw ImmutableActivityLog::make('delete'));
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function scope(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<ActivityLogRelation, $this>
     */
    public function relatedModels(): HasMany
    {
        return $this->hasMany(ActivityLogRelation::class, 'activity_log_id');
    }

    public function isSystem(): bool
    {
        return $this->actor_type === null && $this->actor_role === 'system';
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeInScope(Builder $query, Model $scope): void
    {
        $query->where('scope_type', $scope->getMorphClass())->where('scope_id', $scope->getKey());
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeForSubject(Builder $query, Model $subject): void
    {
        $query->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey());
    }

    /**
     * Entries whose subject is the model or that are related to it.
     *
     * @param Builder<self> $query
     */
    public function scopeInvolving(Builder $query, Model $model): void
    {
        $relations = (new ActivityLogRelation())->newQuery()->toBase()
            ->select('activity_log_id')
            ->where('related_type', $model->getMorphClass())
            ->where('related_id', $model->getKey());

        $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->where('subject_type', $model->getMorphClass())->where('subject_id', $model->getKey()))
            ->orWhereIn($this->getQualifiedKeyName(), $relations));
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeByActor(Builder $query, Model $actor): void
    {
        $query->where('actor_type', $actor->getMorphClass())->where('actor_id', $actor->getKey());
    }

    /**
     * @param Builder<self>        $query
     * @param string|list<string>  $roles
     */
    public function scopeByRole(Builder $query, string|array $roles): void
    {
        $query->whereIn('actor_role', (array) $roles);
    }

    /**
     * @param Builder<self>                                      $query
     * @param string|BackedEnum|list<string|BackedEnum>           $actions
     */
    public function scopeAction(Builder $query, string|BackedEnum|array $actions): void
    {
        $actions = array_map(fn (string|BackedEnum $a): string => $a instanceof BackedEnum ? (string) $a->value : $a, is_array($actions) ? $actions : [$actions]);

        $query->whereIn('action', $actions);
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeBetween(Builder $query, ?DateTimeInterface $from, ?DateTimeInterface $to): void
    {
        $query
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to));
    }
}
