<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function getConnection(): ?string
    {
        return config('activity-log.connection');
    }

    public function up(): void
    {
        Schema::create(config('activity-log.table', 'activity_logs'), function (Blueprint $table): void {
            $table->id();
            $table->string('action');
            $table->uuid('batch_uuid')->nullable()->index();

            // The tenant or case the entry belongs to (a business, an audit case…).
            $table->nullableMorphs('scope');

            $table->nullableMorphs('subject');
            $table->string('subject_label')->nullable();

            // Null actor_type/actor_id = a guest or the system (see actor_role).
            $table->nullableMorphs('actor');
            $table->string('actor_role')->nullable();
            $table->string('actor_label')->nullable();

            $table->json('changes')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->useCurrent()->index();

            $table->index('action');
        });

        Schema::create(config('activity-log.relations_table', 'activity_log_relations'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_log_id')
                ->constrained(config('activity-log.table', 'activity_logs'))
                ->cascadeOnDelete();
            $table->morphs('related');

            $table->unique(['activity_log_id', 'related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('activity-log.relations_table', 'activity_log_relations'));
        Schema::dropIfExists(config('activity-log.table', 'activity_logs'));
    }
};
