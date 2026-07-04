<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_score_predictions', function (Blueprint $table) {
            $table->id();
            $table->string('game_id')->unique();
            $table->unsignedInteger('season');
            $table->date('game_date')->nullable();
            $table->string('home_team_abbr', 16)->nullable();
            $table->string('away_team_abbr', 16)->nullable();
            $table->string('predicted_winner', 8);
            $table->decimal('predicted_home_score', 6, 1);
            $table->decimal('predicted_away_score', 6, 1);
            $table->decimal('predicted_total', 6, 1);
            $table->decimal('predicted_spread', 6, 1);
            $table->decimal('win_probability_home', 5, 1)->nullable();
            $table->decimal('win_probability_away', 5, 1)->nullable();
            $table->decimal('confidence', 5, 1)->nullable();
            $table->timestamp('predicted_at');
            $table->unsignedSmallInteger('actual_home_score')->nullable();
            $table->unsignedSmallInteger('actual_away_score')->nullable();
            $table->string('actual_winner', 8)->nullable();
            $table->boolean('winner_correct')->nullable();
            $table->decimal('home_score_error', 6, 1)->nullable();
            $table->decimal('away_score_error', 6, 1)->nullable();
            $table->decimal('total_error', 6, 1)->nullable();
            $table->boolean('total_within_5')->nullable();
            $table->boolean('spread_direction_correct')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->index(['season', 'graded_at']);
            $table->index('game_date');
        });

        Schema::create('tracked_prop_predictions', function (Blueprint $table) {
            $table->id();
            $table->date('prediction_date');
            $table->string('game_id')->nullable();
            $table->string('player_id');
            $table->string('player_name');
            $table->string('team_abbreviation', 16)->nullable();
            $table->string('opponent', 32)->nullable();
            $table->string('stat_type', 32);
            $table->decimal('line', 6, 1);
            $table->decimal('predicted_value', 6, 1);
            $table->string('recommendation', 16);
            $table->decimal('confidence', 6, 2)->nullable();
            $table->decimal('expected_value', 8, 2)->nullable();
            $table->decimal('probability_over', 6, 2)->nullable();
            $table->decimal('probability_under', 6, 2)->nullable();
            $table->string('betting_value', 16)->nullable();
            $table->text('reasoning')->nullable();
            $table->boolean('is_top_prop')->default(false);
            $table->unsignedSmallInteger('rank')->nullable();
            $table->decimal('actual_value', 6, 1)->nullable();
            $table->boolean('correct')->nullable();
            $table->timestamp('predicted_at');
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['prediction_date', 'player_id', 'stat_type', 'line'],
                'tracked_props_unique_pick'
            );
            $table->index(['prediction_date', 'is_top_prop']);
            $table->index(['graded_at', 'correct']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_prop_predictions');
        Schema::dropIfExists('game_score_predictions');
    }
};
