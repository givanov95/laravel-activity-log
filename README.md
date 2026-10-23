# givanov95/laravel-activity-log

Who did what in a Laravel app: an append-only activity log with explicit actors, a scope, related models and old/new values.

- **Explicit actors** — a user and the role they act in, any other model (a customer through a signed link), a guest, or the system. An explicit actor always wins over the signed-in user.
- **Scope** — the tenant or case an entry belongs to (a business, an audit case, a loan).
- **Subject and related models** — the model the action was done to, plus any other models whose history should show it.
- **Changes** — old/new values from a saved model, from a before/after snapshot (including relation sets such as roles), or by hand; hidden and configured attributes are masked.
- **Labels** — the actor's and subject's names are stored with the entry, so it still reads right after the row is deleted.
- **GDPR** — `scrub()` removes a person's data from the entries that concern them; no IP addresses unless enabled.
- **Append-only** — entries cannot be updated or deleted through the model; they are written synchronously, inside the caller's transaction.

## Install

```bash
composer require givanov95/laravel-activity-log
php artisan migrate
# optional:
php artisan vendor:publish --tag=activity-log-config
```

Requires PHP 8.3+ and Laravel 11, 12 or 13. The migrations are loaded automatically; publish them (`--tag=activity-log-migrations`) only to change them. Morph columns follow your app's morph key type (`Schema::morphUsingUuids()` etc.).

## Recording

```php
use Givanov95\LaravelActivityLog\Actor;
use Givanov95\LaravelActivityLog\Changes;
use Givanov95\LaravelActivityLog\Facades\ActivityLog;

DB::transaction(function () use ($booking, $user, $reason) {
    $booking->update(['status' => 'cancelled', 'cancellation_reason' => $reason]);

    ActivityLog::record('booking.cancelled')        // a string or a backed enum
        ->by(Actor::user($user, 'receptionist'))    // who, and in which role
        ->in($booking->business)                     // scope
        ->on($booking)                               // subject
        ->relatedTo($booking->customer)              // shows in the customer's history too
        ->changes(Changes::fromModel($booking, only: ['status']))
        ->with(['reason' => $reason])                // extra facts
        ->save();
});
```

### Actors

| Factory | Use |
|---|---|
| `Actor::user($user, ?$role, ?$label)` | a signed-in user acting in a role |
| `Actor::model($model, ?$role, ?$label)` | any other model: a customer through a signed link, an API client |
| `Actor::guest(?$label)` | somebody without a record (role `guest`) |
| `Actor::system(?$label)` | a scheduled command, a webhook, a listener (role `system`) |

Without `->by()` the configured resolver decides: by default the signed-in user without a role, and a system actor when nobody is signed in. Implement `Contracts\ResolvesActor` and set `actor_resolver` to add roles. Decide the actor where the caller knows it — in the controller, the command or the signed-link handler — rather than trusting `auth()` deep in a service: on a signed link somebody else may be signed in.

### Labels

A model implementing `Contracts\HasActivityLabel` labels itself; otherwise the first filled attribute of `label_attributes` (`name`, `title`, `display_name`, `email`), then `ClassName #id`. Pass a label explicitly with `->on($model, 'Booking #12, 14:00')` or `Actor::user($user, 'owner', 'Dr. Ivanova')`.

### Changes

```php
Changes::fromModel($model);                        // what the last save() changed — call it after saving
Changes::fromModel($model, only: ['status']);       // or except: [...]
Changes::created($model);                           // all filled attributes as new values
Changes::deleted($model);                           // all filled attributes as old values
Changes::make(['starts_at' => [$old, $new]]);        // by hand; ['old' => …, 'new' => …] works too

$snapshot = Changes::snapshot($user, relations: ['roles' => 'name']);
$user->syncRoles(['editor']);                       // no model events — a snapshot still sees it
$snapshot->diff();                                  // ['roles' => ['old' => ['admin'], 'new' => ['editor']]]

$changes->mask('notes');                            // record that it changed, not the values
```

Values are stored as plain JSON: enums by value, dates as ISO 8601, sets as sorted arrays. Equal old and new values are dropped. A model's `$hidden` attributes and `masked_attributes` are stored as `{"masked": true}`; `ignored_attributes` (timestamps, `remember_token`) are left out.

## Reading

`Models\ActivityLogEntry` has the morph relations `scope`, `subject`, `actor`, the `relations` of related models, and these scopes:

```php
ActivityLogEntry::inScope($business)
    ->involving($customer)               // subject or related
    ->byActor($user)                     // or ->byRole(['owner', 'receptionist'])
    ->action(['booking.cancelled'])      // strings or backed enums
    ->between($from, $to)
    ->latest('id')
    ->paginate();
```

Each entry has `action`, `batch_uuid` (shared by the entries of one request or job; `ActivityLog::newBatch()` starts a new one), `actor_role`, `actor_label`, `subject_label`, `changes`, `properties` and `created_at`. The package has no UI: translate the actions and render the entries in your app.

## Maintenance

Entries refuse `update()` and `delete()`. These go through the query builder on purpose:

```php
// GDPR: mask the person's fields in entries that concern them, drop properties
// that identify them, and relabel them where they are the actor or the subject.
ActivityLog::scrub($customer, fields: ['name', 'email', 'phone'], label: 'Anonymized customer', properties: ['file_name']);

// The tenant is deleted.
ActivityLog::forgetScope($business);
```

`php artisan activity-log:prune` deletes entries older than `retention_days` (or `--days=N`). With `retention_days` null it does nothing, so schedule it only when you want entries to expire.

## Configuration

`config/activity-log.php`: `table`, `relations_table`, `connection`, `actor_resolver`, `label_attributes`, `ignored_attributes`, `masked_attributes`, `store_ip` (default `false`), `retention_days` (default `null`).

## Development

```bash
composer test      # PHPUnit + Testbench (sqlite in-memory)
composer analyse   # Larastan
```

The pre-commit hook comes from `givanov95/laravel-git-hooks`. CI runs `givanov95/ci-workflows` on PHP 8.3 and 8.4.

## License

MIT
