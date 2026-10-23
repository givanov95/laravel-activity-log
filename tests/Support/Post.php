<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    protected $guarded = [];

    protected $hidden = ['secret'];

    protected $casts = [
        'status'       => PostStatus::class,
        'published_at' => 'immutable_datetime',
        'private'      => 'encrypted',
    ];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
