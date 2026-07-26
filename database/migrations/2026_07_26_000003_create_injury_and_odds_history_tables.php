<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only injury status history. Never updated in place: a new row is
        // written whenever a source reports a changed status, so grading can read
        // the status as it existed at any point in time.
        Schema::create('wnba_injury_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id')->nullable(); // wnba_players.id when resolvable
            $table->string('player_external_id')->nullable();
            $table->string('player_name');
            $table->string('team_id')->nullable();
            $table->string('team_abbreviation')->nullable();
            $table->string('status'); // out | doubtful | questionable | probable | available | inactive | unknown
            $table->string('injury_type')->nullable();
            $table->string('body_part')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('reported_at');
            $table->string('source_id');
            $table->string('report_hash', 64);
            $table->unsignedBigInteger('raw_payload_id')->nullable();
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'report_hash'], 'injury_reports_dedupe');
            $table->index(['player_id', 'reported_at']);
            $table->index(['team_id', 'reported_at']);
        });

        // Append-only odds/prop line snapshots so predictions can be graded
        // against the line that existed when the prediction was made.
        Schema::create('wnba_odds_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('source_id');
            $table->string('event_id')->nullable();
            $table->date('game_date')->nullable();
            $table->string('market_type'); // game | player_prop
            $table->string('stat_type')->nullable(); // player_points, player_rebounds, ...
            $table->string('player_name')->nullable();
            $table->string('bookmaker')->nullable();
            $table->decimal('line', 6, 1)->nullable();
            $table->integer('over_odds')->nullable();
            $table->integer('under_odds')->nullable();
            $table->json('payload');
            $table->string('snapshot_hash', 64);
            $table->timestamp('captured_at');
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'snapshot_hash'], 'odds_snapshots_dedupe');
            $table->index(['event_id', 'market_type']);
            $table->index(['player_name', 'stat_type', 'captured_at'], 'odds_snapshots_player_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wnba_odds_snapshots');
        Schema::dropIfExists('wnba_injury_reports');
    }
};
