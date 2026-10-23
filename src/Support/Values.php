<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Support;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use Stringable;
use UnitEnum;

/**
 * Turns attribute values into plain JSON values, so an entry reads the same
 * whatever casts the model has later.
 *
 * @internal
 */
final class Values
{
    public static function normalize(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_scalar($value) => $value,
            $value instanceof BackedEnum       => $value->value,
            $value instanceof UnitEnum         => $value->name,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof Model            => $value->getKey(),
            $value instanceof Arrayable        => self::normalize($value->toArray()),
            $value instanceof JsonSerializable => self::normalize($value->jsonSerialize()),
            is_array($value)                   => array_map(self::normalize(...), $value),
            $value instanceof Stringable       => (string) $value,
            default                            => null,
        };
    }

    /**
     * The value of one attribute as the model would cast it, from a raw
     * (database) value.
     */
    public static function cast(Model $model, string $key, mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        try {
            $copy = $model->newInstance();
            $copy->setRawAttributes([$key => $raw]);

            return self::normalize($copy->getAttribute($key));
        } catch (\Throwable) {
            return self::normalize($raw);
        }
    }
}
