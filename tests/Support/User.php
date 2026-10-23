<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $guarded = [];

    protected $hidden = ['password'];
}
