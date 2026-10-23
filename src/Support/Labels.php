<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Support;

use Givanov95\LaravelActivityLog\Contracts\HasActivityLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @internal
 */
final class Labels
{
    public static function for(Model $model): string
    {
        if ($model instanceof HasActivityLabel) {
            return self::limit($model->activityLabel());
        }

        $attributes = $model->getAttributes();

        foreach ((array) config('activity-log.label_attributes', []) as $attribute) {
            $value = array_key_exists($attribute, $attributes) ? $model->getAttribute($attribute) : null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                return self::limit((string) $value);
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    public static function limit(?string $label): ?string
    {
        return $label === null ? null : Str::limit($label, 250);
    }
}
