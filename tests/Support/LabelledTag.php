<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Tests\Support;

use Givanov95\LaravelActivityLog\Contracts\HasActivityLabel;

class LabelledTag extends Tag implements HasActivityLabel
{
    protected $table = 'tags';
}
