<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Tests\Support;

use Givanov95\LaravelActivityLog\Contracts\HasActivityLabel;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function activityLabel(): string
    {
        return 'Tag: '.$this->name;
    }
}
