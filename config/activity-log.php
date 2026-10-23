<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Tables and connection
    |--------------------------------------------------------------------------
    |
    | Where the entries and their related models are stored. Leave the
    | connection null to write on the default one: an entry then joins the
    | transaction of the change it describes.
    |
    */

    'table'           => 'activity_logs',
    'relations_table' => 'activity_log_relations',
    'connection'      => null,

    /*
    |--------------------------------------------------------------------------
    | Actor resolver
    |--------------------------------------------------------------------------
    |
    | Used only when an entry is saved without ->by(). The default takes the
    | authenticated user (no role); with nobody signed in the entry is a
    | system one. Point it at your own class implementing
    | Givanov95\LaravelActivityLog\Contracts\ResolvesActor to add roles.
    |
    */

    'actor_resolver' => Givanov95\LaravelActivityLog\Support\AuthActorResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Labels
    |--------------------------------------------------------------------------
    |
    | An entry keeps a label of its actor and subject, so it still reads
    | right after the row is deleted. A model can implement
    | Givanov95\LaravelActivityLog\Contracts\HasActivityLabel; otherwise the
    | first filled attribute below is used, then "ClassName #id".
    |
    */

    'label_attributes' => ['name', 'title', 'display_name', 'email'],

    /*
    |--------------------------------------------------------------------------
    | Changes
    |--------------------------------------------------------------------------
    |
    | Attributes never compared, and attributes whose values are never
    | stored (only that they changed). A model's $hidden attributes are
    | always masked too.
    |
    */

    'ignored_attributes' => ['created_at', 'updated_at', 'deleted_at', 'remember_token'],

    'masked_attributes' => ['password', 'two_factor_secret', 'two_factor_recovery_codes'],

    /*
    |--------------------------------------------------------------------------
    | IP address
    |--------------------------------------------------------------------------
    |
    | An IP address is personal data. Off by default.
    |
    */

    'store_ip' => false,

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Days after which `php artisan activity-log:prune` deletes entries.
    | Null keeps everything (the command then does nothing without --days).
    |
    */

    'retention_days' => null,

];
