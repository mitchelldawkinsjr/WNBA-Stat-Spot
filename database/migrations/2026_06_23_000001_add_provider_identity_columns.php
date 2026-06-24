<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wnba_players', function (Blueprint $table) {
            $table->string('espn_athlete_id')->nullable()->unique()->after('athlete_id');
            $table->string('tank01_player_id')->nullable()->unique()->after('espn_athlete_id');
        });

        Schema::table('wnba_teams', function (Blueprint $table) {
            $table->string('espn_team_id')->nullable()->unique()->after('team_id');
            $table->string('tank01_team_id')->nullable()->unique()->after('espn_team_id');
        });

        Schema::table('wnba_games', function (Blueprint $table) {
            $table->string('espn_game_id')->nullable()->unique()->after('game_id');
            $table->string('tank01_game_id')->nullable()->unique()->after('espn_game_id');
        });

        $this->backfillIdentities();
    }

    public function down(): void
    {
        Schema::table('wnba_games', function (Blueprint $table) {
            $table->dropColumn(['espn_game_id', 'tank01_game_id']);
        });

        Schema::table('wnba_teams', function (Blueprint $table) {
            $table->dropColumn(['espn_team_id', 'tank01_team_id']);
        });

        Schema::table('wnba_players', function (Blueprint $table) {
            $table->dropColumn(['espn_athlete_id', 'tank01_player_id']);
        });
    }

    private function backfillIdentities(): void
    {
        if (! Schema::hasTable('wnba_players')) {
            return;
        }

        DB::table('wnba_players')->orderBy('id')->chunkById(500, function ($players) {
            foreach ($players as $player) {
                $id = (string) $player->athlete_id;
                $updates = [];

                if ($this->looksLikeEspnPlayerId($id)) {
                    $updates['espn_athlete_id'] = $id;
                } elseif ($this->looksLikeTank01PlayerId($id)) {
                    $updates['tank01_player_id'] = $id;
                }

                if ($updates !== []) {
                    DB::table('wnba_players')->where('id', $player->id)->update($updates);
                }
            }
        });

        DB::table('wnba_games')->orderBy('id')->chunkById(500, function ($games) {
            foreach ($games as $game) {
                $id = (string) $game->game_id;
                $updates = [];

                if ($this->looksLikeEspnGameId($id)) {
                    $updates['espn_game_id'] = $id;
                } elseif ($this->looksLikeTank01GameId($id)) {
                    $updates['tank01_game_id'] = $id;
                }

                if ($updates !== []) {
                    DB::table('wnba_games')->where('id', $game->id)->update($updates);
                }
            }
        });
    }

    private function looksLikeEspnPlayerId(string $id): bool
    {
        return ctype_digit($id) && strlen($id) >= 7;
    }

    private function looksLikeTank01PlayerId(string $id): bool
    {
        return ctype_digit($id) && strlen($id) <= 6;
    }

    private function looksLikeEspnTeamId(string $id): bool
    {
        return false;
    }

    private function looksLikeTank01TeamId(string $id): bool
    {
        return false;
    }

    private function looksLikeEspnGameId(string $id): bool
    {
        return ctype_digit($id) && strlen($id) >= 9;
    }

    private function looksLikeTank01GameId(string $id): bool
    {
        return str_contains($id, '@');
    }
};
