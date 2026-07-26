<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LINEAGE_TABLES = [
        'wnba_games',
        'wnba_game_teams',
        'wnba_player_games',
        'wnba_plays',
    ];

    public function up(): void
    {
        foreach (self::LINEAGE_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('source_id')->nullable();
                $table->unsignedBigInteger('raw_payload_id')->nullable();
                $table->timestamp('ingested_at')->nullable();
                $table->string('validation_status')->nullable(); // valid | warning | invalid
            });
        }

        Schema::table('wnba_players', function (Blueprint $table) {
            $table->string('sportsblaze_player_id')->nullable()->unique();
        });

        Schema::table('wnba_teams', function (Blueprint $table) {
            $table->string('sportsblaze_team_id')->nullable()->unique();
        });

        Schema::table('wnba_games', function (Blueprint $table) {
            $table->string('sportsblaze_game_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('wnba_games', function (Blueprint $table) {
            $table->dropColumn('sportsblaze_game_id');
        });

        Schema::table('wnba_teams', function (Blueprint $table) {
            $table->dropColumn('sportsblaze_team_id');
        });

        Schema::table('wnba_players', function (Blueprint $table) {
            $table->dropColumn('sportsblaze_player_id');
        });

        foreach (self::LINEAGE_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['source_id', 'raw_payload_id', 'ingested_at', 'validation_status']);
            });
        }
    }
};
