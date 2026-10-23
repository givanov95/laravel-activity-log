<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Support;

use Givanov95\LaravelActivityLog\Actor;
use Givanov95\LaravelActivityLog\Contracts\ResolvesActor;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * The default resolver: the signed-in user of the default guard, without a
 * role. Pass ->by() wherever the role matters or the person acting is not
 * the signed-in user (a signed link, a webhook, a scheduled command).
 */
final class AuthActorResolver implements ResolvesActor
{
    public function __construct(private readonly Container $container)
    {
    }

    public function resolve(): ?Actor
    {
        if (! $this->container->bound('auth')) {
            return null;
        }

        $user = $this->container->make(AuthFactory::class)->guard()->user();

        return $user instanceof Model ? Actor::user($user) : null;
    }
}
