<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A model an entry concerns besides its subject.
 *
 * @property int             $id
 * @property int             $activity_log_id
 * @property string          $related_type
 * @property int|string      $related_id
 */
class ActivityLogRelation extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    public function getTable(): string
    {
        return (string) config('activity-log.relations_table', 'activity_log_relations');
    }

    public function getConnectionName(): ?string
    {
        return config('activity-log.connection') ?? parent::getConnectionName();
    }

    /**
     * @return BelongsTo<ActivityLogEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ActivityLogEntry::class, 'activity_log_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
