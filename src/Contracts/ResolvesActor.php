<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Contracts;

use Givanov95\LaravelActivityLog\Actor;

/**
 * Supplies the actor of an entry saved without ->by(). Return null when
 * there is nobody to blame: the entry is then a system one.
 */
interface ResolvesActor
{
    public function resolve(): ?Actor;
}
