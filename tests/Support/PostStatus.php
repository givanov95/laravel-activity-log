<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Tests\Support;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
