<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog;

use Givanov95\LaravelActivityLog\Support\Values;
use Illuminate\Database\Eloquent\Model;

/**
 * A model's state before a change. Made by Changes::snapshot().
 */
final class Snapshot
{
    /**
     * @param list<string>|null                    $only
     * @param array<string, string|null>           $relations
     * @param array<string, mixed>                 $before
     */
    private function __construct(
        private readonly Model $model,
        private readonly ?array $only,
        private readonly array $relations,
        private readonly array $before,
    ) {
    }

    /**
     * @param list<string>|null         $only
     * @param array<int|string, string> $relations
     */
    public static function take(Model $model, ?array $only = null, array $relations = []): self
    {
        $normalized = [];

        foreach ($relations as $relation => $attribute) {
            is_int($relation)
                ? $normalized[$attribute] = null
                : $normalized[$relation] = $attribute;
        }

        return new self($model, $only, $normalized, self::state($model, $normalized));
    }

    /**
     * The changes between the snapshot and the model now. Pass the model
     * again when the change was saved through another instance.
     */
    public function diff(?Model $after = null): Changes
    {
        $after ??= $this->model;
        $now = self::state($after, $this->relations);
        $changes = [];

        foreach (array_unique([...array_keys($this->before), ...array_keys($now)]) as $field) {
            $changes[$field] = ['old' => $this->before[$field] ?? null, 'new' => $now[$field] ?? null];
        }

        return Changes::filtered($after, $changes, $this->only === null ? null : [...$this->only, ...array_keys($this->relations)], []);
    }

    /**
     * @param array<string, string|null> $relations
     *
     * @return array<string, mixed>
     */
    private static function state(Model $model, array $relations): array
    {
        $state = Values::castAll($model, $model->getAttributes());

        foreach ($relations as $relation => $attribute) {
            $query = $model->{$relation}();
            $related = $query->getRelated();

            $state[$relation] = $query->pluck($related->qualifyColumn($attribute ?? $related->getKeyName()))
                ->map(fn (mixed $value): mixed => Values::normalize($value))
                ->sort()
                ->values()
                ->all();
        }

        return $state;
    }
}
