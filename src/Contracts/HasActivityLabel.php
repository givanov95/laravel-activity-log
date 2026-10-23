<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Contracts;

/**
 * How a model reads in the log when it is an entry's actor or subject.
 * The label is stored with the entry, so it outlives the row.
 */
interface HasActivityLabel
{
    public function activityLabel(): string;
}
