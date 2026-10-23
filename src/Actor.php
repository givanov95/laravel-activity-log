<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog;

use Givanov95\LaravelActivityLog\Support\Labels;
use Illuminate\Database\Eloquent\Model;

/**
 * Who did it. Built where the caller knows it: a controller knows whether
 * the signed-in user acts as an owner or a receptionist, a signed link knows
 * the customer, a scheduled command is the system. An explicit actor always
 * wins over the configured resolver.
 */
final class Actor
{
    private function __construct(
        public readonly ?Model $model,
        public readonly ?string $role,
        private readonly ?string $label,
    ) {
    }

    /**
     * A signed-in user, optionally with the role they act in.
     */
    public static function user(Model $user, ?string $role = null, ?string $label = null): self
    {
        return new self($user, $role, $label);
    }

    /**
     * Any other model that acts: a customer through a signed link, an API
     * client, an integration.
     */
    public static function model(Model $model, ?string $role = null, ?string $label = null): self
    {
        return new self($model, $role, $label);
    }

    /**
     * Somebody without a record (a widget visitor), described by the label.
     */
    public static function guest(?string $label = null, string $role = 'guest'): self
    {
        return new self(null, $role, $label);
    }

    /**
     * The application itself: a scheduled command, a webhook, a listener.
     */
    public static function system(?string $label = null, string $role = 'system'): self
    {
        return new self(null, $role, $label);
    }

    public function label(): ?string
    {
        if ($this->label !== null) {
            return Labels::limit($this->label);
        }

        return $this->model === null ? null : Labels::for($this->model);
    }

    public function isSystem(): bool
    {
        return $this->model === null && $this->role === 'system';
    }
}
