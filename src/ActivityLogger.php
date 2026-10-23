<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog;

use BackedEnum;
use Givanov95\LaravelActivityLog\Contracts\ResolvesActor;
use Givanov95\LaravelActivityLog\Models\ActivityLogEntry;
use Givanov95\LaravelActivityLog\Models\ActivityLogRelation;
use Givanov95\LaravelActivityLog\Support\Labels;
use Givanov95\LaravelActivityLog\Support\Values;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

/**
 * Writes and maintains the log. Bound as a scoped instance, so every entry
 * of one request or job shares a batch uuid.
 */
class ActivityLogger
{
    private ?string $batchUuid = null;

    public function __construct(private readonly Container $container)
    {
    }

    public function record(string|BackedEnum $action): PendingActivity
    {
        return new PendingActivity($this, $action);
    }

    /**
     * @internal called by PendingActivity::save()
     */
    public function write(PendingActivity $pending): ActivityLogEntry
    {
        $actor = $pending->actor() ?? $this->resolveActor();
        $subject = $pending->subject();
        $scope = $pending->scope();
        $changes = $pending->changeSet();
        $properties = Values::normalize($pending->properties());

        $entry = new ActivityLogEntry();
        $entry->forceFill([
            'action'        => $pending->action,
            'batch_uuid'    => $this->batchUuid(),
            'scope_type'    => $scope?->getMorphClass(),
            'scope_id'      => $scope?->getKey(),
            'subject_type'  => $subject?->getMorphClass(),
            'subject_id'    => $subject?->getKey(),
            'subject_label' => Labels::limit($pending->subjectLabel() ?? ($subject === null ? null : Labels::for($subject))),
            'actor_type'    => $actor->model?->getMorphClass(),
            'actor_id'      => $actor->model?->getKey(),
            'actor_role'    => $actor->role,
            'actor_label'   => $actor->label(),
            'changes'       => $changes->isEmpty() ? null : $changes->toArray(),
            'properties'    => $properties === [] ? null : $properties,
            'ip_address'    => $this->ip(),
        ])->save();

        foreach ($pending->related() as $related) {
            $relation = new ActivityLogRelation();
            $relation->forceFill([
                'activity_log_id' => $entry->getKey(),
                'related_type'    => $related->getMorphClass(),
                'related_id'      => $related->getKey(),
            ])->save();
        }

        return $entry;
    }

    /**
     * The uuid shared by the entries of this request or job.
     */
    public function batchUuid(): string
    {
        return $this->batchUuid ??= (string) Str::uuid();
    }

    /**
     * Start a new batch (e.g. per item of a long-running command).
     */
    public function newBatch(): string
    {
        return $this->batchUuid = (string) Str::uuid();
    }

    /**
     * Remove personal data of a model from the entries that concern it (its
     * subject, actor, scope or related entries): the values of $fields become
     * masked, the $properties keys are dropped, and where the model is the
     * actor or the subject its stored label becomes $label. Returns the
     * number of entries changed.
     *
     * @param list<string> $fields
     * @param list<string> $properties
     */
    public function scrub(Model $model, array $fields = [], ?string $label = null, array $properties = []): int
    {
        $type = $model->getMorphClass();
        $id = $model->getKey();
        $count = 0;

        $this->entries()
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q->where('subject_type', $type)->where('subject_id', $id))
                ->orWhere(fn (Builder $q) => $q->where('actor_type', $type)->where('actor_id', $id))
                ->orWhere(fn (Builder $q) => $q->where('scope_type', $type)->where('scope_id', $id))
                ->orWhereIn('id', $this->relationsQuery()->select('activity_log_id')->where('related_type', $type)->where('related_id', $id)))
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($type, $id, $fields, $label, $properties, &$count): void {
                foreach ($rows as $row) {
                    $update = [];

                    $changes = $row->changes === null ? null : json_decode((string) $row->changes, true);
                    if (is_array($changes)) {
                        $scrubbed = $changes;
                        foreach ($fields as $field) {
                            if (array_key_exists($field, $scrubbed)) {
                                $scrubbed[$field] = ['masked' => true];
                            }
                        }
                        if ($scrubbed !== $changes) {
                            $update['changes'] = json_encode($scrubbed);
                        }
                    }

                    $props = $row->properties === null ? null : json_decode((string) $row->properties, true);
                    if (is_array($props) && $properties !== []) {
                        $kept = array_diff_key($props, array_flip($properties));
                        if ($kept !== $props) {
                            $update['properties'] = $kept === [] ? null : json_encode($kept);
                        }
                    }

                    if ($label !== null && $row->actor_type === $type && (string) $row->actor_id === (string) $id && $row->actor_label !== $label) {
                        $update['actor_label'] = Labels::limit($label);
                    }

                    if ($label !== null && $row->subject_type === $type && (string) $row->subject_id === (string) $id && $row->subject_label !== $label) {
                        $update['subject_label'] = Labels::limit($label);
                    }

                    if ($update !== []) {
                        $this->entries()->where('id', $row->id)->update($update);
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * Delete every entry of a scope (e.g. when the tenant is deleted).
     */
    public function forgetScope(Model $scope): int
    {
        return $this->deleteWhere(fn (Builder $q) => $q->where('scope_type', $scope->getMorphClass())->where('scope_id', $scope->getKey()));
    }

    /**
     * Delete entries older than the given number of days.
     */
    public function prune(int $days): int
    {
        $before = now()->subDays($days);

        return $this->deleteWhere(fn (Builder $q) => $q->where('created_at', '<', $before));
    }

    private function deleteWhere(\Closure $where): int
    {
        $deleted = 0;

        // Through the query builder on purpose: the model refuses deletes.
        do {
            $ids = $this->entries()->where($where)->orderBy('id')->limit(1000)->pluck('id')->all();

            if ($ids !== []) {
                $this->relationsQuery()->whereIn('activity_log_id', $ids)->delete();
                $deleted += $this->entries()->whereIn('id', $ids)->delete();
            }
        } while (count($ids) === 1000);

        return $deleted;
    }

    private function resolveActor(): Actor
    {
        $resolver = $this->container->make(ResolvesActor::class);

        return $resolver->resolve() ?? Actor::system();
    }

    private function ip(): ?string
    {
        if (! config('activity-log.store_ip', false) || ! $this->container->bound('request')) {
            return null;
        }

        return $this->container->make('request')->ip();
    }

    private function entries(): Builder
    {
        return (new ActivityLogEntry())->newQuery()->toBase();
    }

    private function relationsQuery(): Builder
    {
        return (new ActivityLogRelation())->newQuery()->toBase();
    }
}
