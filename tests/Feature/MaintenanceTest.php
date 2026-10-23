<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Tests\Feature;

use Givanov95\LaravelActivityLog\Actor;
use Givanov95\LaravelActivityLog\Exceptions\ImmutableActivityLog;
use Givanov95\LaravelActivityLog\Facades\ActivityLog;
use Givanov95\LaravelActivityLog\Models\ActivityLogEntry;
use Givanov95\LaravelActivityLog\Models\ActivityLogRelation;
use Givanov95\LaravelActivityLog\Tests\Support\Post;
use Givanov95\LaravelActivityLog\Tests\Support\Tag;
use Givanov95\LaravelActivityLog\Tests\Support\User;
use Givanov95\LaravelActivityLog\Tests\TestCase;

class MaintenanceTest extends TestCase
{
    public function test_entries_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $entry = ActivityLog::record('x')->save();

        try {
            $entry->forceFill(['action' => 'y'])->save();
            $this->fail('An update went through.');
        } catch (ImmutableActivityLog) {
        }

        $this->expectException(ImmutableActivityLog::class);
        $entry->delete();
    }

    public function test_query_scopes(): void
    {
        $scope = Tag::create(['name' => 'Tenant']);
        $otherScope = Tag::create(['name' => 'Other tenant']);
        $post = Post::create(['title' => 'P']);
        $related = Post::create(['title' => 'R']);
        $user = User::create(['name' => 'Ana', 'email' => 'a@example.test']);

        $a = ActivityLog::record('post.created')->in($scope)->on($post)->by(Actor::user($user, 'owner'))->save();
        $b = ActivityLog::record('post.updated')->in($scope)->on($related)->relatedTo($post)->by(Actor::system())->save();
        $c = ActivityLog::record('post.created')->in($otherScope)->on($related)->by(Actor::guest())->save();

        $ids = fn ($q) => $q->orderBy('id')->pluck('id')->all();

        $this->assertSame([$a->id, $b->id], $ids(ActivityLogEntry::inScope($scope)));
        $this->assertSame([$a->id], $ids(ActivityLogEntry::forSubject($post)));
        $this->assertSame([$a->id, $b->id], $ids(ActivityLogEntry::involving($post)));
        $this->assertSame([$a->id], $ids(ActivityLogEntry::byActor($user)));
        $this->assertSame([$b->id], $ids(ActivityLogEntry::byRole('system')));
        $this->assertSame([$a->id, $c->id], $ids(ActivityLogEntry::action('post.created')));
        $this->assertSame([], $ids(ActivityLogEntry::between(now()->addDay(), null)));
        $this->assertSame([$a->id, $b->id, $c->id], $ids(ActivityLogEntry::between(now()->subMinute(), now()->addMinute())));
    }

    public function test_scrub_masks_fields_drops_properties_and_relabels_the_model(): void
    {
        $customer = User::create(['name' => 'Maria Petrova', 'email' => 'maria@example.test']);
        $post = Post::create(['title' => 'P']);

        $asSubject = ActivityLog::record('customer.updated')->on($customer)
            ->changes(['name' => ['Maria', 'Maria Petrova'], 'email' => ['m@x.test', 'maria@example.test'], 'language' => ['bg', 'en']])->save();
        $asActor = ActivityLog::record('post.cancelled')->by(Actor::model($customer, 'customer'))->on($post)->save();
        $asRelated = ActivityLog::record('file.uploaded')->on($post)->relatedTo($customer)->with(['file_name' => 'maria-xray.jpg', 'size' => 10])->save();
        $unrelated = ActivityLog::record('file.uploaded')->on($post)->with(['file_name' => 'other.jpg'])->save();

        $count = ActivityLog::scrub($customer, fields: ['name', 'email'], label: 'Anonymized customer', properties: ['file_name']);

        $this->assertSame(3, $count);
        $this->assertSame([
            'name'     => ['masked' => true],
            'email'    => ['masked' => true],
            'language' => ['old' => 'bg', 'new' => 'en'],
        ], $asSubject->fresh()->changes);
        $this->assertSame('Anonymized customer', $asSubject->fresh()->subject_label);
        $this->assertSame('Anonymized customer', $asActor->fresh()->actor_label);
        $this->assertSame('P', $asActor->fresh()->subject_label);
        $this->assertSame(['size' => 10], $asRelated->fresh()->properties);
        $this->assertSame(['file_name' => 'other.jpg'], $unrelated->fresh()->properties);
        $this->assertSame(0, ActivityLog::scrub($customer, fields: ['name', 'email'], label: 'Anonymized customer', properties: ['file_name']));
    }

    public function test_forget_scope_deletes_only_that_scope_with_its_relations(): void
    {
        $scope = Tag::create(['name' => 'Tenant']);
        $other = Tag::create(['name' => 'Other']);
        $post = Post::create(['title' => 'P']);

        ActivityLog::record('a')->in($scope)->relatedTo($post)->save();
        ActivityLog::record('b')->in($scope)->save();
        $kept = ActivityLog::record('c')->in($other)->relatedTo($post)->save();

        $this->assertSame(2, ActivityLog::forgetScope($scope));
        $this->assertSame([$kept->id], ActivityLogEntry::pluck('id')->all());
        $this->assertSame(1, ActivityLogRelation::count());
    }

    public function test_prune_command_uses_the_option_or_the_config(): void
    {
        $this->travelTo(now()->subDays(40));
        ActivityLog::record('old')->relatedTo(Post::create(['title' => 'P']))->save();
        $this->travelBack();
        ActivityLog::record('new')->save();

        $this->artisan('activity-log:prune')->expectsOutputToContain('nothing was pruned')->assertSuccessful();
        $this->assertSame(2, ActivityLogEntry::count());

        config(['activity-log.retention_days' => 30]);
        $this->artisan('activity-log:prune')->assertSuccessful();
        $this->assertSame(['new'], ActivityLogEntry::pluck('action')->all());
        $this->assertSame(0, ActivityLogRelation::count());

        $this->artisan('activity-log:prune', ['--days' => '0'])->assertFailed();
    }
}
