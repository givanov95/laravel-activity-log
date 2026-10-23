<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Tests\Feature;

use Carbon\CarbonImmutable;
use Givanov95\LaravelActivityLog\Changes;
use Givanov95\LaravelActivityLog\Tests\Support\Post;
use Givanov95\LaravelActivityLog\Tests\Support\PostStatus;
use Givanov95\LaravelActivityLog\Tests\Support\Tag;
use Givanov95\LaravelActivityLog\Tests\Support\User;
use Givanov95\LaravelActivityLog\Tests\TestCase;

class ChangesTest extends TestCase
{
    public function test_make_accepts_both_shapes_and_drops_equal_values(): void
    {
        $changes = Changes::make([
            'a' => ['x', 'y'],
            'b' => ['old' => 1, 'new' => 2],
            'c' => ['same', 'same'],
            'd' => ['masked' => true],
        ]);

        $this->assertSame([
            'a' => ['old' => 'x', 'new' => 'y'],
            'b' => ['old' => 1, 'new' => 2],
            'd' => ['masked' => true],
        ], $changes->toArray());
    }

    public function test_make_refuses_a_malformed_change(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Changes::make(['a' => 'just a value']);
    }

    public function test_from_model_reads_the_last_save_with_casts_and_ignores_timestamps(): void
    {
        $this->requiresGetPrevious();

        $post = Post::create(['title' => 'Old', 'status' => PostStatus::Draft]);
        $this->travel(1)->minutes();

        $post->update(['title' => 'New', 'status' => PostStatus::Published, 'published_at' => CarbonImmutable::parse('2026-10-23 10:00:00', 'UTC')]);

        $this->assertSame([
            'title'        => ['old' => 'Old', 'new' => 'New'],
            'status'       => ['old' => 'draft', 'new' => 'published'],
            'published_at' => ['old' => null, 'new' => '2026-10-23T10:00:00+00:00'],
        ], Changes::fromModel($post)->toArray());
    }

    public function test_hidden_and_configured_attributes_are_masked(): void
    {
        $this->requiresGetPrevious();

        $post = Post::create(['title' => 'A']);
        $post->update(['secret' => 'x1', 'title' => 'B']);
        $user = User::create(['name' => 'Ana', 'email' => 'a@example.test', 'password' => 'one']);
        $user->update(['password' => 'two']);

        $this->assertSame(['masked' => true], Changes::fromModel($post)->toArray()['secret']);
        $this->assertSame(['password' => ['masked' => true]], Changes::fromModel($user)->toArray());
    }

    public function test_only_except_mask_and_merge(): void
    {
        $this->requiresGetPrevious();

        $post = Post::create(['title' => 'A', 'body' => 'x']);
        $post->update(['title' => 'B', 'body' => 'y']);

        $this->assertSame(['title'], array_keys(Changes::fromModel($post, only: ['title'])->toArray()));
        $this->assertSame(['title'], array_keys(Changes::fromModel($post, except: ['body'])->toArray()));
        $this->assertSame(['masked' => true], Changes::fromModel($post)->mask('body')->toArray()['body']);
        $this->assertTrue(Changes::fromModel($post)->only(['title'])->merge(['body' => ['x', 'y']])->has('body'));
        $this->assertTrue(Changes::none()->isEmpty());
    }

    public function test_created_and_deleted_keep_the_values(): void
    {
        $post = Post::create(['title' => 'A', 'secret' => 's']);

        $created = Changes::created($post)->toArray();
        $this->assertSame(['old' => null, 'new' => 'A'], $created['title']);
        $this->assertSame(['masked' => true], $created['secret']);
        $this->assertArrayNotHasKey('id', $created);
        $this->assertArrayNotHasKey('created_at', $created);

        $this->assertSame(['old' => 'A', 'new' => null], Changes::deleted($post)->toArray()['title']);
    }

    public function test_a_snapshot_diffs_attributes_and_related_sets(): void
    {
        $post = Post::create(['title' => 'A']);
        $red = Tag::create(['name' => 'red']);
        $blue = Tag::create(['name' => 'blue']);
        $green = Tag::create(['name' => 'green']);
        $post->tags()->attach([$red->id, $blue->id]);

        $snapshot = Changes::snapshot($post, relations: ['tags' => 'name']);
        $post->update(['title' => 'B']);
        $post->tags()->sync([$blue->id, $green->id]);

        $this->assertSame([
            'title' => ['old' => 'A', 'new' => 'B'],
            'tags'  => ['old' => ['blue', 'red'], 'new' => ['blue', 'green']],
        ], $snapshot->diff()->toArray());
    }

    public function test_a_snapshot_without_changes_is_empty_and_only_limits_attributes(): void
    {
        $post = Post::create(['title' => 'A', 'body' => 'x']);
        $snapshot = Changes::snapshot($post, only: ['title'], relations: ['tags']);

        $this->assertTrue($snapshot->diff()->isEmpty());

        Post::whereKey($post->id)->update(['body' => 'y', 'title' => 'C']);

        $this->assertSame(['title' => ['old' => 'A', 'new' => 'C']], $snapshot->diff($post->fresh())->toArray());
    }

    public function test_from_model_explains_itself_on_laravel_11(): void
    {
        if (method_exists(Post::class, 'getPrevious')) {
            $this->markTestSkipped('Laravel 12+ has Model::getPrevious().');
        }

        $post = Post::create(['title' => 'A']);
        $post->update(['title' => 'B']);

        $this->expectException(\LogicException::class);
        Changes::fromModel($post);
    }

    private function requiresGetPrevious(): void
    {
        if (! method_exists(Post::class, 'getPrevious')) {
            $this->markTestSkipped('Changes::fromModel() needs Laravel 12+.');
        }
    }

    public function test_make_masks_the_configured_attributes(): void
    {
        $this->assertSame(['password' => ['masked' => true]], Changes::make(['password' => ['old', 'new']])->toArray());
    }

    public function test_a_snapshot_does_not_report_unchanged_hidden_fields(): void
    {
        $user = User::create(['name' => 'Ana', 'email' => 'a@example.test', 'password' => 'secret']);
        $snapshot = Changes::snapshot($user);

        $user->update(['name' => 'Ana B']);

        $this->assertSame(['name' => ['old' => 'Ana', 'new' => 'Ana B']], $snapshot->diff()->toArray());
    }

    public function test_a_snapshot_treats_equal_values_of_another_type_as_unchanged(): void
    {
        $post = Post::create(['title' => 'A', 'views' => 3]);
        $post = Post::find($post->id);
        $snapshot = Changes::snapshot($post);

        $post->fill(['views' => '3', 'title' => 'B'])->save();

        $this->assertSame(['title'], array_keys($snapshot->diff()->toArray()));
    }

    public function test_encrypted_columns_are_masked(): void
    {
        $post = Post::create(['title' => 'A', 'private' => 'diagnosis']);

        $this->assertSame(['masked' => true], Changes::created($post)->toArray()['private']);
        $this->assertStringNotContainsString('diagnosis', (string) json_encode(Changes::created($post)));
    }
}
