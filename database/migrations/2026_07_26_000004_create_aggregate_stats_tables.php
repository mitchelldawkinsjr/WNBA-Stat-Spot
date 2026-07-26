<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Precomputed aggregates written only by the Analytics Agent.
        // Percentage columns are decimals between 0 and 1; null means the
        // denominator was zero or the inputs were unavailable.
        Schema::create('wnba_player_season_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('wnba_players', 'id');
            $table->integer('season');
            $table->string('team_id')->nullable();
            $table->integer('games_played');
            $table->integer('games_started');
            $table->decimal('minutes_total', 8, 1)->nullable();
            $table->integer('points_total');
            $table->integer('rebounds_total');
            $table->integer('offensive_rebounds_total');
            $table->integer('defensive_rebounds_total');
            $table->integer('assists_total');
            $table->integer('steals_total');
            $table->integer('blocks_total');
            $table->integer('turnovers_total');
            $table->integer('fouls_total');
            $table->integer('field_goals_made_total');
            $table->integer('field_goals_attempted_total');
            $table->integer('three_point_made_total');
            $table->integer('three_point_attempted_total');
            $table->integer('free_throws_made_total');
            $table->integer('free_throws_attempted_total');
            $table->decimal('points_avg', 6, 2)->nullable();
            $table->decimal('rebounds_avg', 6, 2)->nullable();
            $table->decimal('assists_avg', 6, 2)->nullable();
            $table->decimal('steals_avg', 6, 2)->nullable();
            $table->decimal('blocks_avg', 6, 2)->nullable();
            $table->decimal('turnovers_avg', 6, 2)->nullable();
            $table->decimal('minutes_avg', 6, 2)->nullable();
            $table->decimal('fg_pct', 5, 4)->nullable();
            $table->decimal('three_pct', 5, 4)->nullable();
            $table->decimal('ft_pct', 5, 4)->nullable();
            $table->decimal('efg_pct', 5, 4)->nullable();
            $table->decimal('ts_pct', 5, 4)->nullable();
            $table->json('splits')->nullable(); // home/away, per-36
            $table->string('formula_version');
            $table->timestamp('computed_at');
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'season']);
            $table->index(['season', 'points_avg']);
        });

        Schema::create('wnba_team_season_stats', function (Blueprint $table) {
            $table->id();
            $table->string('team_id');
            $table->integer('season');
            $table->integer('games_played');
            $table->integer('wins');
            $table->integer('losses');
            $table->decimal('points_for_avg', 6, 2)->nullable();
            $table->decimal('points_against_avg', 6, 2)->nullable();
            $table->decimal('pace', 6, 2)->nullable();
            $table->decimal('possessions_per_game', 6, 2)->nullable();
            $table->decimal('offensive_rating', 6, 2)->nullable();
            $table->decimal('defensive_rating', 6, 2)->nullable();
            $table->decimal('net_rating', 6, 2)->nullable();
            // Four Factors (offense) and their defensive counterparts.
            $table->decimal('efg_pct', 5, 4)->nullable();
            $table->decimal('tov_pct', 5, 4)->nullable();
            $table->decimal('oreb_pct', 5, 4)->nullable();
            $table->decimal('ft_rate', 5, 4)->nullable();
            $table->decimal('opp_efg_pct', 5, 4)->nullable();
            $table->decimal('opp_tov_pct', 5, 4)->nullable();
            $table->decimal('dreb_pct', 5, 4)->nullable();
            $table->decimal('opp_ft_rate', 5, 4)->nullable();
            $table->decimal('ts_pct', 5, 4)->nullable();
            $table->decimal('three_rate', 5, 4)->nullable();
            $table->json('splits')->nullable(); // home/away
            $table->string('formula_version');
            $table->timestamp('computed_at');
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'season']);
        });

        Schema::create('wnba_player_game_advanced', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_game_id')->unique()->constrained('wnba_player_games', 'id');
            $table->foreignId('game_id')->constrained('wnba_games', 'id');
            $table->foreignId('player_id')->constrained('wnba_players', 'id');
            $table->decimal('minutes_decimal', 5, 2)->nullable();
            $table->decimal('ts_pct', 5, 4)->nullable();
            $table->decimal('efg_pct', 5, 4)->nullable();
            $table->decimal('usage_pct', 5, 4)->nullable();
            $table->decimal('game_score', 6, 2)->nullable();
            $table->decimal('points_per_shot', 6, 3)->nullable();
            $table->string('formula_version');
            $table->timestamp('computed_at');
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->timestamps();
        });

        Schema::create('wnba_matchup_summaries', function (Blueprint $table) {
            $table->id();
            $table->integer('season');
            // team_a_id is always the lexically smaller id so each pair has one row.
            $table->string('team_a_id');
            $table->string('team_b_id');
            $table->integer('games_played');
            $table->integer('team_a_wins');
            $table->integer('team_b_wins');
            $table->decimal('avg_total_points', 6, 2)->nullable();
            $table->decimal('avg_margin', 6, 2)->nullable();
            $table->decimal('avg_pace', 6, 2)->nullable();
            $table->date('last_meeting_date')->nullable();
            $table->json('recent_meetings')->nullable();
            $table->string('formula_version');
            $table->timestamp('computed_at');
            $table->unsignedBigInteger('agent_run_id')->nullable();
            $table->timestamps();

            $table->unique(['season', 'team_a_id', 'team_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wnba_matchup_summaries');
        Schema::dropIfExists('wnba_player_game_advanced');
        Schema::dropIfExists('wnba_team_season_stats');
        Schema::dropIfExists('wnba_player_season_stats');
    }
};
