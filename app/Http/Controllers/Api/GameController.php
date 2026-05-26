<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WnbaGame;
use Illuminate\Http\JsonResponse;

class GameController extends Controller
{
    public function index(): JsonResponse
    {
        $games = WnbaGame::with(['gameTeams.team', 'gameTeams.opponentTeam'])
            ->orderBy('game_date', 'desc')
            ->get();

        // Transform the data to match frontend expectations
        $transformedGames = $games->map(function ($game) {
            return [
                'id' => $game->id,
                'game_id' => $game->game_id,
                'season' => (string) $game->season, // Convert to string
                'season_type' => $this->getSeasonTypeName($game->season_type), // Convert to readable string
                'game_date' => $game->game_date,
                'game_date_time' => $game->game_date_time,
                'venue_name' => $game->venue_name,
                'venue_city' => $game->venue_city,
                'venue_state' => $game->venue_state,
                'status_name' => $game->status_name,
                'created_at' => $game->created_at,
                'updated_at' => $game->updated_at,
                // Include team information for richer display
                'home_team' => $this->getTeamInfo($game, 'home'),
                'away_team' => $this->getTeamInfo($game, 'away'),
                'final_score' => $this->getFinalScore($game),
            ];
        });

        return response()->json([
            'data' => $transformedGames,
            'message' => 'Games retrieved successfully'
        ]);
    }

    private function getSeasonTypeName($seasonType): string
    {
        return match($seasonType) {
            1 => 'Preseason',
            2 => 'Regular Season',
            3 => 'Playoffs',
            4 => 'Finals',
            default => 'Unknown'
        };
    }

    private function getTeamInfo($game, $homeAway)
    {
        $gameTeam = $game->gameTeams->where('home_away', $homeAway)->first();

        if (!$gameTeam || !$gameTeam->team) {
            return null;
        }

        return [
            'id' => $gameTeam->team->id,
            'team_id' => $gameTeam->team->team_id,
            'name' => $gameTeam->team->team_display_name,
            'abbreviation' => $gameTeam->team->team_abbreviation,
            'logo' => $gameTeam->team->team_logo,
            'score' => $gameTeam->team_score,
            'winner' => $gameTeam->team_winner,
        ];
    }

    private function getFinalScore($game)
    {
        $homeTeam = $game->gameTeams->where('home_away', 'home')->first();
        $awayTeam = $game->gameTeams->where('home_away', 'away')->first();

        if (!$homeTeam || !$awayTeam) {
            return null;
        }

        return [
            'home' => $homeTeam->team_score,
            'away' => $awayTeam->team_score,
            'final' => $game->status_name === 'STATUS_FINAL',
        ];
    }
}
