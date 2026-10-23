<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Tests\Feature;

use Givanov95\LaravelActivityLog\Actor;
use Givanov95\LaravelActivityLog\Contracts\ResolvesActor;
use Givanov95\LaravelActivityLog\Facades\ActivityLog;
use Givanov95\LaravelActivityLog\Models\ActivityLogEntry;
use Givanov95\LaravelActivityLog\Tests\Support\LabelledTag;
use Givanov95\LaravelActivityLog\Tests\Support\Post;
use Givanov95\LaravelActivityLog\Tests\Support\PostStatus;
use Givanov95\LaravelActivityLog\Tests\Support\Tag;
use Givanov95\LaravelActivityLog\Tests\Support\User;
use Givanov95\LaravelActivityLog\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class RecordingTest extends TestCase
{
    private function user(string $name = 'Ana'): User
    {
        return User::create(['name' => $name, 'email' => strtolower($name).'@example.test', 'password' => 'secret']);
    }

    public function test_it_records_actor_scope_subject_related_changes_and_properties(): void
    {
        $user = $this->user();
        $scope = Tag::create(['name' => 'Tenant']);
        $post = Post::create(['title' => 'Hello']);
        $other = Tag::create(['name' => 'Other']);

        $entry = ActivityLog::record('post.published')
            ->by(Actor::user($user, 'editor'))
            ->in($scope)
            ->on($post)
            ->relatedTo($other, [$user], null)
            ->changes(['status' => ['draft', 'published']])
            ->with(['channel' => 'web'])
            ->with('reason', PostStatus::Published)
            ->save();

        $entry->refresh();
        $this->assertSame('post.published', $entry->action);
        $this->assertSame($user->getMorphClass(), $entry->actor_type);
        $this->assertEquals($user->id, $entry->actor_id);
        $this->assertSame('editor', $entry->actor_role);
        $this->assertSame('Ana', $entry->actor_label);
        $this->assertTrue($entry->scope->is($scope));
        $this->assertTrue($entry->subject->is($post));
        $this->assertSame('Hello', $entry->subject_label);
        $this->assertSame(['status' => ['old' => 'draft', 'new' => 'published']], $entry->changes);
        $this->assertSame(['channel' => 'web', 'reason' => 'published'], $entry->properties);
        $this->assertCount(2, $entry->relatedModels);
        $this->assertNotNull($entry->batch_uuid);
        $this->assertNull($entry->ip_address);
    }

    public function test_enum_actions_and_explicit_labels(): void
    {
        $entry = ActivityLog::record(PostStatus::Published)
            ->by(Actor::guest('Visitor ana@example.test'))
            ->on(Post::create(['title' => 'Hi']), 'Post “Hi”')
            ->save();

        $this->assertSame('published', $entry->action);
        $this->assertNull($entry->actor_type);
        $this->assertSame('guest', $entry->actor_role);
        $this->assertSame('Visitor ana@example.test', $entry->actor_label);
        $this->assertSame('Post “Hi”', $entry->subject_label);
    }

    public function test_labels_come_from_the_contract_then_attributes_then_class_and_key(): void
    {
        $labelled = LabelledTag::create(['name' => 'vip']);
        $unnamed = Post::create(['title' => '']);

        $this->assertSame('Tag: vip', ActivityLog::record('x')->on($labelled)->save()->subject_label);
        $this->assertSame('Post #'.$unnamed->id, ActivityLog::record('x')->on($unnamed)->save()->subject_label);
    }

    public function test_without_by_the_signed_in_user_is_the_actor(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $entry = ActivityLog::record('x')->save();

        $this->assertEquals($user->id, $entry->actor_id);
        $this->assertNull($entry->actor_role);
    }

    public function test_without_by_and_without_a_user_the_entry_is_a_system_one(): void
    {
        $entry = ActivityLog::record('x')->save();

        $this->assertNull($entry->actor_type);
        $this->assertSame('system', $entry->actor_role);
        $this->assertTrue($entry->isSystem());
    }

    public function test_an_explicit_system_or_model_actor_is_not_replaced_by_the_signed_in_user(): void
    {
        $this->actingAs($this->user('Signed'));
        $customer = $this->user('Customer');

        $system = ActivityLog::record('x')->by(Actor::system('nightly cleanup'))->save();
        $model = ActivityLog::record('x')->by(Actor::model($customer, 'customer'))->save();

        $this->assertNull($system->actor_id);
        $this->assertSame('system', $system->actor_role);
        $this->assertSame('nightly cleanup', $system->actor_label);
        $this->assertEquals($customer->id, $model->actor_id);
        $this->assertSame('Customer', $model->actor_label);
    }

    public function test_a_custom_resolver_can_add_the_role(): void
    {
        $user = $this->user();
        $this->app->bind(ResolvesActor::class, fn () => new class($user) implements ResolvesActor {
            public function __construct(private User $user)
            {
            }

            public function resolve(): ?Actor
            {
                return Actor::user($this->user, 'admin');
            }
        });

        $this->assertSame('admin', ActivityLog::record('x')->save()->actor_role);
    }

    public function test_entries_of_one_request_share_a_batch_until_a_new_one_starts(): void
    {
        $a = ActivityLog::record('a')->save();
        $b = ActivityLog::record('b')->save();
        ActivityLog::newBatch();
        $c = ActivityLog::record('c')->save();

        $this->assertSame($a->batch_uuid, $b->batch_uuid);
        $this->assertNotSame($a->batch_uuid, $c->batch_uuid);
    }

    public function test_the_ip_is_stored_only_when_enabled(): void
    {
        config(['activity-log.store_ip' => true]);
        $this->app['request']->server->set('REMOTE_ADDR', '10.1.2.3');

        $this->assertSame('10.1.2.3', ActivityLog::record('x')->save()->ip_address);
    }

    public function test_an_entry_rolls_back_with_the_transaction_it_was_written_in(): void
    {
        try {
            DB::transaction(function (): void {
                ActivityLog::record('x')->save();

                throw new \RuntimeException('fail');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, ActivityLogEntry::count());
    }
}
