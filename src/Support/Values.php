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
        return self::castAll($model, [$key => $raw])[$key];
    }

    /**
     * Raw (database) attributes as the model would cast them, through one
     * copy of the model.
     *
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    public static function castAll(Model $model, array $raw): array
    {
        $copy = $model->newInstance();
        $copy->setRawAttributes($raw);
        $values = [];

        foreach ($raw as $key => $value) {
            try {
                $values[$key] = $value === null ? null : self::normalize($copy->getAttribute($key));
            } catch (\Throwable) {
                $values[$key] = self::normalize($value);
            }
        }

        return $values;
    }

    /**
     * Whether two normalized values mean the same, the way Eloquent sees it:
     * 3 and "3", 1.5 and "1.50", true and 1 are equal.
     */
    public static function equivalent(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (is_bool($a) || is_bool($b)) {
            $other = is_bool($a) ? $b : $a;

            return in_array($other, [0, 1, '0', '1'], true) && (bool) $a === (bool) $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (string) (0 + $a) === (string) (0 + $b);
        }

        return false;
    }
}
