<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog;

use BackedEnum;
use Givanov95\LaravelActivityLog\Models\ActivityLogEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * An entry being built: ActivityLog::record('booking.cancelled')->by(…)->on(…)->save().
 */
final class PendingActivity
{
    private ?Actor $actor = null;

    private ?Model $scope = null;

    private ?Model $subject = null;

    private ?string $subjectLabel = null;

    /** @var array<string, Model> */
    private array $related = [];

    private ?Changes $changes = null;

    /** @var array<string, mixed> */
    private array $properties = [];

    public readonly string $action;

    public function __construct(private readonly ActivityLogger $logger, string|BackedEnum $action)
    {
        $this->action = $action instanceof BackedEnum ? (string) $action->value : $action;
    }

    public function by(Actor $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    /**
     * The tenant or case the entry belongs to.
     */
    public function in(?Model $scope): self
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * The model the action was done to. The label is stored, so the entry
     * still reads right after the model is deleted.
     */
    public function on(?Model $subject, ?string $label = null): self
    {
        $this->subject = $subject;
        $this->subjectLabel = $label;

        return $this;
    }

    /**
     * Other models the entry concerns: it shows in their history and is
     * scrubbed with them.
     *
     * @param Model|iterable<Model>|null ...$models
     */
    public function relatedTo(Model|iterable|null ...$models): self
    {
        foreach ($models as $model) {
            foreach ($model instanceof Model ? [$model] : ($model ?? []) as $one) {
                if ($one->getKey() !== null) {
                    $this->related[$one->getMorphClass().':'.$one->getKey()] = $one;
                }
            }
        }

        return $this;
    }

    /**
     * @param Changes|array<string, mixed> $changes
     */
    public function changes(Changes|array $changes): self
    {
        $this->changes = $changes instanceof Changes ? $changes : Changes::make($changes);

        return $this;
    }

    /**
     * Extra facts about the action (a reason, a file name, a channel).
     *
     * @param array<string, mixed>|string $key
     */
    public function with(array|string $key, mixed $value = null): self
    {
        $this->properties = is_array($key) ? [...$this->properties, ...$key] : [...$this->properties, $key => $value];

        return $this;
    }

    public function save(): ActivityLogEntry
    {
        return $this->logger->write($this);
    }

    public function actor(): ?Actor
    {
        return $this->actor;
    }

    public function scope(): ?Model
    {
        return $this->scope;
    }

    public function subject(): ?Model
    {
        return $this->subject;
    }

    public function subjectLabel(): ?string
    {
        return $this->subjectLabel;
    }

    /**
     * @return list<Model>
     */
    public function related(): array
    {
        return array_values($this->related);
    }

    public function changeSet(): Changes
    {
        return $this->changes ?? Changes::none();
    }

    /**
     * @return array<string, mixed>
     */
    public function properties(): array
    {
        return $this->properties;
    }
}
