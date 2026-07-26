<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Player stats split by opponent team season DRtg bucket.
        // Written only by the Analytics Agent.
        Schema::create('wnba_player_vs_defense', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('wnba_players', 'id');
            $table->integer('season');
            // elite | good | average | poor
            $table->string('defense_bucket', 16);
            $table->integer('games');
            $table->decimal('minutes_avg', 6, 2)->nullable();
            $table->decimal('points_avg', 6, 2)->nullable();
            $table->decimal('rebounds_avg', 6, 2)->nullable();
            $table->decimal('assists_avg', 6, 2)->nullable();
            $table->decimal('fg_pct', 5, 4)->nullable();
            $table->decimal('three_pct', 5, 4)->nullable();
            $table->decimal('ts_pct', 5, 4)->nullable();
            $table->decimal('usage_pct_avg', 5, 4)->nullable();
            $table->string('formula_version');
            $table->timestamp('computed_at');
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'season', 'defense_bucket'], 'player_vs_defense_unique');
            $table->index(['season', 'defense_bucket']);
        });

        Schema::create('wnba_player_performance_trends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('wnba_players', 'id');
            $table->integer('season');
            // l5 | l10 | season
            $table->string('window', 16);
            $table->integer('games');
            $table->decimal('minutes_avg', 6, 2)->nullable();
            $table->decimal('points_avg', 6, 2)->nullable();
            $table->decimal('rebounds_avg', 6, 2)->nullable();
            $table->decimal('assists_avg', 6, 2)->nullable();
            $table->decimal('fg_pct', 5, 4)->nullable();
            $table->decimal('ts_pct', 5, 4)->nullable();
            $table->decimal('points_slope', 8, 4)->nullable();
            $table->decimal('rebounds_slope', 8, 4)->nullable();
            $table->decimal('assists_slope', 8, 4)->nullable();
            $table->string('formula_version');
            $table->timestamp('computed_at');
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'season', 'window'], 'player_perf_trends_unique');
        });

        Schema::create('wnba_team_performance_trends', function (Blueprint $table) {
            $table->id();
            $table->string('team_id');
            $table->integer('season');
            // l5 | l10 | season
            $table->string('window', 16);
            $table->integer('games');
            $table->integer('wins');
            $table->integer('losses');
            $table->decimal('points_for_avg', 6, 2)->nullable();
            $table->decimal('points_against_avg', 6, 2)->nullable();
            $table->decimal('pace_avg', 6, 2)->nullable();
            $table->decimal('offensive_rating', 6, 2)->nullable();
            $table->decimal('defensive_rating', 6, 2)->nullable();
            $table->string('formula_version');
            $table->timestamp('computed_at');
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'season', 'window'], 'team_perf_trends_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wnba_team_performance_trends');
        Schema::dropIfExists('wnba_player_performance_trends');
        Schema::dropIfExists('wnba_player_vs_defense');
    }
};
