<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wnba_team_power_rankings', function (Blueprint $table) {
            $table->id();
            $table->integer('season');
            $table->date('as_of_date');
            $table->string('team_id');
            $table->unsignedSmallInteger('rank');
            $table->unsignedSmallInteger('previous_rank')->nullable();
            $table->smallInteger('rank_delta')->nullable();
            $table->decimal('score', 8, 3);
            $table->json('components')->nullable();
            $table->string('reason')->nullable();
            $table->string('formula_version');
            $table->timestamp('computed_at');
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->timestamps();

            $table->unique(['season', 'as_of_date', 'team_id'], 'team_power_rankings_unique');
            $table->index(['season', 'as_of_date', 'rank']);
        });

        Schema::create('wnba_daily_insights', function (Blueprint $table) {
            $table->id();
            $table->integer('season');
            $table->date('insight_date');
            $table->string('insight_type', 32);
            $table->string('entity_type', 16);
            $table->string('entity_id');
            $table->string('title');
            $table->text('body');
            $table->unsignedSmallInteger('priority')->default(50);
            $table->json('payload')->nullable();
            $table->string('formula_version');
            $table->timestamp('computed_at');
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->timestamps();

            $table->index(['season', 'insight_date', 'priority']);
            $table->index(['entity_type', 'entity_id', 'insight_date']);
        });

        Schema::create('wnba_player_percentile_ranks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('wnba_players', 'id');
            $table->integer('season');
            $table->unsignedSmallInteger('sample_size');
            $table->decimal('points_pctl', 5, 1)->nullable();
            $table->decimal('rebounds_pctl', 5, 1)->nullable();
            $table->decimal('assists_pctl', 5, 1)->nullable();
            $table->decimal('steals_pctl', 5, 1)->nullable();
            $table->decimal('blocks_pctl', 5, 1)->nullable();
            $table->decimal('minutes_pctl', 5, 1)->nullable();
            $table->decimal('ts_pct_pctl', 5, 1)->nullable();
            $table->decimal('efg_pct_pctl', 5, 1)->nullable();
            $table->string('formula_version');
            $table->timestamp('computed_at');
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'season']);
            $table->index(['season', 'points_pctl']);
        });

        Schema::create('wnba_team_percentile_ranks', function (Blueprint $table) {
            $table->id();
            $table->string('team_id');
            $table->integer('season');
            $table->unsignedSmallInteger('sample_size');
            $table->decimal('offensive_rating_pctl', 5, 1)->nullable();
            $table->decimal('defensive_rating_pctl', 5, 1)->nullable();
            $table->decimal('net_rating_pctl', 5, 1)->nullable();
            $table->decimal('pace_pctl', 5, 1)->nullable();
            $table->decimal('efg_pct_pctl', 5, 1)->nullable();
            $table->decimal('tov_pct_pctl', 5, 1)->nullable();
            $table->string('formula_version');
            $table->timestamp('computed_at');
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'season']);
            $table->index(['season', 'net_rating_pctl']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wnba_team_percentile_ranks');
        Schema::dropIfExists('wnba_player_percentile_ranks');
        Schema::dropIfExists('wnba_daily_insights');
        Schema::dropIfExists('wnba_team_power_rankings');
    }
};
