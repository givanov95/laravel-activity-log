<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog;

use Countable;
use Givanov95\LaravelActivityLog\Support\Values;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

/**
 * Old and new values of the fields an action changed:
 * `['status' => ['old' => 'confirmed', 'new' => 'cancelled']]`. A masked
 * field records only that it changed: `['notes' => ['masked' => true]]`.
 * Immutable; every method returns a new instance.
 *
 * @implements Arrayable<string, array<string, mixed>>
 */
final class Changes implements Arrayable, Countable, JsonSerializable
{
    /**
     * @param array<string, array<string, mixed>> $items
     */
    private function __construct(private readonly array $items)
    {
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * From `field => [old, new]` or `field => ['old' => …, 'new' => …]`.
     * Equal old and new values are dropped.
     *
     * @param array<string, mixed> $changes
     */
    public static function make(array $changes): self
    {
        $masked = (array) config('activity-log.masked_attributes', []);
        $items = [];

        foreach ($changes as $field => $change) {
            if (in_array($field, $masked, true) || (is_array($change) && ($change['masked'] ?? false) === true)) {
                $items[$field] = ['masked' => true];

                continue;
            }

            if (is_array($change) && (array_key_exists('old', $change) || array_key_exists('new', $change))) {
                [$old, $new] = [$change['old'] ?? null, $change['new'] ?? null];
            } elseif (is_array($change) && array_is_list($change) && count($change) === 2) {
                [$old, $new] = $change;
            } else {
                throw new \InvalidArgumentException("The change of [{$field}] must be [old, new] or ['old' => …, 'new' => …].");
            }

            $old = Values::normalize($old);
            $new = Values::normalize($new);

            if ($old !== $new) {
                $items[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return new self($items);
    }

    /**
     * What the last save() of the model changed. Call it after saving.
     * Needs Laravel 12+ (Model::getPrevious()); on Laravel 11 take a
     * snapshot() before the change and diff() it after.
     *
     * @param list<string>|null $only
     * @param list<string>      $except
     */
    public static function fromModel(Model $model, ?array $only = null, array $except = []): self
    {
        // Checked at run time: the package supports Laravel 11, analysed on the latest.
        static $hasPrevious = null;
        $hasPrevious ??= (new \ReflectionClass(Model::class))->hasMethod('getPrevious');

        if (! $hasPrevious) {
            throw new \LogicException('Changes::fromModel() needs Laravel 12 or later. On Laravel 11 use Changes::snapshot($model) before the change and ->diff() after it.');
        }

        $keys = array_keys($model->getChanges());
        $previous = $model->getPrevious();
        $old = Values::castAll($model, array_combine($keys, array_map(fn (string $key): mixed => $previous[$key] ?? null, $keys)));
        $new = Values::castAll($model, array_combine($keys, array_map(fn (string $key): mixed => $model->getAttributes()[$key] ?? null, $keys)));
        $changes = [];

        foreach ($keys as $key) {
            $changes[$key] = ['old' => $old[$key], 'new' => $new[$key]];
        }

        return self::filtered($model, $changes, $only, $except);
    }

    /**
     * Every filled attribute of a model that was just created, as new values.
     *
     * @param list<string>|null $only
     * @param list<string>      $except
     */
    public static function created(Model $model, ?array $only = null, array $except = []): self
    {
        return self::filtered($model, self::attributes($model, 'new'), $only, $except);
    }

    /**
     * Every filled attribute of a model about to be deleted, as old values.
     *
     * @param list<string>|null $only
     * @param list<string>      $except
     */
    public static function deleted(Model $model, ?array $only = null, array $except = []): self
    {
        return self::filtered($model, self::attributes($model, 'old'), $only, $except);
    }

    /**
     * Remember a model (and the sets of related models) before a change, to
     * diff() after it. Catches what model events miss: syncRoles(), attach(),
     * detach(), a hasMany rewritten in place.
     *
     * @param list<string>|null                $only
     * @param array<int|string, string>         $relations `['roles' => 'name', 'tags']`: the related attribute to compare, by default its key
     */
    public static function snapshot(Model $model, ?array $only = null, array $relations = []): Snapshot
    {
        return Snapshot::take($model, $only, $relations);
    }

    /**
     * Record that these fields changed, without their values.
     */
    public function mask(string ...$fields): self
    {
        $items = $this->items;

        foreach ($fields as $field) {
            if (array_key_exists($field, $items)) {
                $items[$field] = ['masked' => true];
            }
        }

        return new self($items);
    }

    /**
     * @param list<string> $fields
     */
    public function only(array $fields): self
    {
        return new self(array_intersect_key($this->items, array_flip($fields)));
    }

    /**
     * @param list<string> $fields
     */
    public function except(array $fields): self
    {
        return new self(array_diff_key($this->items, array_flip($fields)));
    }

    /**
     * @param self|array<string, mixed> $other
     */
    public function merge(self|array $other): self
    {
        $other = $other instanceof self ? $other : self::make($other);

        return new self([...$this->items, ...$other->items]);
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    /**
     * @internal
     *
     * @param array<string, array{old: mixed, new: mixed}> $changes
     * @param list<string>|null                            $only
     * @param list<string>                                 $except
     */
    public static function filtered(Model $model, array $changes, ?array $only, array $except): self
    {
        $ignored = [...(array) config('activity-log.ignored_attributes', []), ...$except];
        $masked = self::maskedFor($model);
        $items = [];

        foreach ($changes as $field => $change) {
            if (in_array($field, $ignored, true) || ($only !== null && ! in_array($field, $only, true))) {
                continue;
            }

            // Unchanged first: a hidden field that did not change is not logged.
            if (Values::equivalent($change['old'], $change['new'])) {
                continue;
            }

            $items[$field] = in_array($field, $masked, true) ? ['masked' => true] : $change;
        }

        return new self($items);
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    public static function maskedFor(Model $model): array
    {
        // Encrypted columns stay encrypted: the log never holds their plaintext.
        $encrypted = array_keys(array_filter(
            $model->getCasts(),
            fn (string $cast): bool => str_starts_with($cast, 'encrypted') || str_contains($cast, 'AsEncrypted'),
        ));

        return array_values(array_unique([...(array) config('activity-log.masked_attributes', []), ...$model->getHidden(), ...$encrypted]));
    }

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private static function attributes(Model $model, string $side): array
    {
        $changes = [];
        $raw = array_filter($model->getAttributes(), fn (mixed $value, string $key): bool => $value !== null && $key !== $model->getKeyName(), ARRAY_FILTER_USE_BOTH);

        foreach (Values::castAll($model, $raw) as $key => $value) {
            $changes[$key] = $side === 'new' ? ['old' => null, 'new' => $value] : ['old' => $value, 'new' => null];
        }

        return $changes;
    }
}
