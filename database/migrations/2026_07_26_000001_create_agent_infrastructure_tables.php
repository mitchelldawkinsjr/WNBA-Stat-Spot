<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Untouched provider responses. Written before normalization so every
        // canonical value is auditable; hash prevents storing unchanged responses.
        Schema::create('wnba_raw_payloads', function (Blueprint $table) {
            $table->id();
            $table->string('source_id');
            $table->string('entity_type');
            $table->string('endpoint')->nullable();
            $table->integer('season')->nullable();
            $table->string('content_hash', 64);
            $table->longText('payload');
            $table->integer('record_count')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['source_id', 'entity_type', 'content_hash'], 'raw_payloads_dedupe');
            $table->index(['source_id', 'entity_type', 'season']);
        });

        // Field-level disagreements between sources. Rows with requires_review=true
        // double as the human review queue.
        Schema::create('wnba_data_conflicts', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->string('entity_key');
            $table->string('field');
            $table->json('candidates');
            $table->string('selected_value')->nullable();
            $table->string('selected_source')->nullable();
            $table->string('resolution_reason')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->boolean('requires_review')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_key']);
            $table->index('requires_review');
        });

        // Structured run summaries for every agent invocation (cron, CLI, or API).
        Schema::create('wnba_agent_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_uuid')->unique();
            $table->string('agent'); // data | analytics | entity
            $table->string('mode');  // incremental | backfill | repair | audit | live
            $table->string('status')->default('running'); // running | success | partial | failed
            $table->integer('season')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->boolean('dry_run')->default(false);
            $table->json('counters')->nullable();
            $table->json('warnings')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['agent', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wnba_agent_runs');
        Schema::dropIfExists('wnba_data_conflicts');
        Schema::dropIfExists('wnba_raw_payloads');
    }
};
