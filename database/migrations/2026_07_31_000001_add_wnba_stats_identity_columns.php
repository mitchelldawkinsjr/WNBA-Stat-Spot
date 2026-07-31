<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wnba_games', function (Blueprint $table) {
            $table->string('wnba_stats_game_id')->nullable()->unique()->after('sportsblaze_game_id');
        });

        Schema::table('wnba_players', function (Blueprint $table) {
            $table->string('wnba_stats_player_id')->nullable()->unique()->after('sportsblaze_player_id');
        });

        Schema::table('wnba_teams', function (Blueprint $table) {
            $table->string('wnba_stats_team_id')->nullable()->unique()->after('sportsblaze_team_id');
        });
    }

    public function down(): void
    {
        Schema::table('wnba_games', function (Blueprint $table) {
            $table->dropColumn('wnba_stats_game_id');
        });

        Schema::table('wnba_players', function (Blueprint $table) {
            $table->dropColumn('wnba_stats_player_id');
        });

        Schema::table('wnba_teams', function (Blueprint $table) {
            $table->dropColumn('wnba_stats_team_id');
        });
    }
};
