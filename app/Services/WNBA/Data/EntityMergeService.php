<?php

namespace App\Services\WNBA\Data;

use App\Models\WnbaGame;
use App\Models\WnbaPlayer;
use App\Models\WnbaTeam;
use App\Services\WNBA\Data\Providers\EspnWnbaProvider;
use App\Services\WNBA\Data\Providers\Tank01WnbaProvider;
use App\Services\WNBA\Data\Support\TeamCatalog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EntityMergeService
{
  /** @var array<string, string> */
  private array $teamByTank01 = [];

  /** @var array<string, string> */
  private array $teamByEspn = [];

  /** @var array<string, string> */
  private array $teamByAbv = [];

  /** @var array<string, string> */
  private array $playerByTank01 = [];

  /** @var array<string, string> */
  private array $playerByEspn = [];

  /** @var array<string, string> */
  private array $gameByTank01 = [];

  /** @var array<string, string> */
  private array $gameByEspn = [];

  private bool $cachesLoaded = false;

  public function loadCaches(): void
  {
    if ($this->cachesLoaded) {
      return;
    }

    foreach (WnbaTeam::query()->get(['team_id', 'espn_team_id', 'tank01_team_id', 'team_abbreviation']) as $team) {
      $canonical = (string) $team->team_id;
      $canonicalAbv = TeamCatalog::canonicalAbbreviation((string) $team->team_abbreviation);
      $this->teamByAbv[$canonicalAbv] = $canonical;

      foreach (TeamCatalog::aliasesFor($canonicalAbv) as $alias) {
        $this->teamByAbv[$alias] = $canonical;
      }

      if ($team->espn_team_id) {
        $this->teamByEspn[(string) $team->espn_team_id] = $canonical;
      }

      if ($team->tank01_team_id) {
        $this->teamByTank01[(string) $team->tank01_team_id] = $canonical;
      }

      if ($this->looksLikeEspnTeamId($canonical)) {
        $this->teamByEspn[$canonical] = $canonical;
      } elseif ($this->looksLikeTank01TeamId($canonical)) {
        $this->teamByTank01[$canonical] = $canonical;
      }
    }

    foreach (WnbaPlayer::query()->get(['athlete_id', 'espn_athlete_id', 'tank01_player_id']) as $player) {
      $canonical = (string) $player->athlete_id;

      if ($player->espn_athlete_id) {
        $this->playerByEspn[(string) $player->espn_athlete_id] = $canonical;
      }

      if ($player->tank01_player_id) {
        $this->playerByTank01[(string) $player->tank01_player_id] = $canonical;
      }

      if ($this->looksLikeEspnPlayerId($canonical)) {
        $this->playerByEspn[$canonical] = $canonical;
      } elseif ($this->looksLikeTank01PlayerId($canonical)) {
        $this->playerByTank01[$canonical] = $canonical;
      }
    }

    foreach (WnbaGame::query()->get(['game_id', 'espn_game_id', 'tank01_game_id']) as $game) {
      $canonical = (string) $game->game_id;

      if ($game->espn_game_id) {
        $this->gameByEspn[(string) $game->espn_game_id] = $canonical;
      }

      if ($game->tank01_game_id) {
        $this->gameByTank01[(string) $game->tank01_game_id] = $canonical;
      }

      if ($this->looksLikeEspnGameId($canonical)) {
        $this->gameByEspn[$canonical] = $canonical;
      } elseif ($this->looksLikeTank01GameId($canonical)) {
        $this->gameByTank01[$canonical] = $canonical;
      }
    }

    $this->cachesLoaded = true;
  }

  /**
   * @return array{teams: int, players: int, games: int}
   */
  public function syncAll(int $season): array
  {
    $teams = $this->syncTeamMappings();
    $players = $this->syncPlayerMappings($season);
    $games = $this->syncGameMappings($season);
    $this->cachesLoaded = false;
    $this->loadCaches();

    return compact('teams', 'players', 'games');
  }

  public function syncTeamMappings(): int
  {
    $espnTeams = app(EspnWnbaProvider::class)->fetchTeams((int) config('wnba.seasons.current_season'));
    $tank01Teams = $this->safeTank01Teams();

    $merged = 0;

    foreach ($espnTeams as $espnTeam) {
      $abv = TeamCatalog::canonicalAbbreviation((string) ($espnTeam['team_abbreviation'] ?? ''));
      $espnId = (string) ($espnTeam['team_id'] ?? '');
      if ($espnId === '') {
        continue;
      }

      $tank01Team = TeamCatalog::matchTank01Team($espnTeam, $tank01Teams);
      $tank01Id = $tank01Team ? (string) ($tank01Team['team_id'] ?? '') : null;
      $canonicalId = $espnId;

      $existing = WnbaTeam::query()
        ->where('espn_team_id', $espnId)
        ->orWhere('team_id', $espnId)
        ->when($tank01Id, fn ($q) => $q->orWhere('tank01_team_id', $tank01Id)->orWhere('team_id', $tank01Id))
        ->first();

      $payload = [
        'team_id' => $canonicalId,
        'espn_team_id' => $espnId,
        'tank01_team_id' => $tank01Id,
        'team_name' => $espnTeam['team_name'] ?? $tank01Team['team_name'] ?? 'Unknown',
        'team_location' => $espnTeam['team_location'] ?? $tank01Team['team_location'] ?? 'Unknown',
        'team_abbreviation' => $abv ?: 'UNK',
        'team_display_name' => $espnTeam['team_display_name'] ?? $tank01Team['team_display_name'] ?? 'Unknown Team',
        'team_uid' => $espnTeam['team_uid'] ?? null,
        'team_slug' => $espnTeam['team_slug'] ?? null,
        'team_logo' => $espnTeam['team_logo'] ?? null,
        'team_color' => $espnTeam['team_color'] ?? null,
        'team_alternate_color' => $espnTeam['team_alternate_color'] ?? null,
      ];

      if ($existing && $existing->team_id !== $canonicalId) {
        $this->repointTeamForeignKeys($existing->team_id, $canonicalId);
        $existing->update($payload);
      } else {
        WnbaTeam::updateOrCreate(['team_id' => $canonicalId], $payload);
      }

      $merged++;
    }

    WnbaTeam::query()
      ->whereNull('espn_team_id')
      ->whereIn('team_id', collect($espnTeams)->pluck('team_id')->filter()->all())
      ->get()
      ->each(fn (WnbaTeam $team) => $team->update(['espn_team_id' => $team->team_id]));

    return $merged;
  }

  public function syncPlayerMappings(int $season): int
  {
    $espn = app(EspnWnbaProvider::class);
    $tank01 = app(Tank01WnbaProvider::class);
    $merged = 0;

    $tank01Roster = [];
    $tank01Teams = $this->safeTank01Teams($season);
    $tank01AbvById = [];
    foreach ($tank01Teams as $team) {
      $tank01AbvById[(string) ($team['team_id'] ?? '')] = TeamCatalog::canonicalAbbreviation((string) ($team['team_abbreviation'] ?? ''));
    }

    foreach ($this->safeTank01RosterPlayers() as $player) {
      $abv = $tank01AbvById[(string) ($player['team_id'] ?? '')] ?? '';
      $key = $this->playerMatchKey(
        (string) ($player['athlete_display_name'] ?? ''),
        (string) ($player['athlete_jersey'] ?? ''),
        $abv,
      );
      $tank01Roster[$key] = $player;
    }

    foreach ($espn->fetchTeams($season) as $team) {
      $espnTeamId = (string) ($team['team_id'] ?? '');
      if ($espnTeamId === '') {
        continue;
      }

      $canonicalTeamId = $this->resolveTeamId($espnTeamId, 'espn');
      $teamAbv = TeamCatalog::canonicalAbbreviation((string) ($team['team_abbreviation'] ?? ''));

      foreach ($espn->fetchRosterPlayers($espnTeamId) as $espnPlayer) {
        $espnId = (string) ($espnPlayer['athlete_id'] ?? '');
        if ($espnId === '') {
          continue;
        }

        $matchKey = $this->playerMatchKey(
          (string) ($espnPlayer['athlete_display_name'] ?? ''),
          (string) ($espnPlayer['athlete_jersey'] ?? ''),
          $teamAbv,
        );
        $tank01Player = $tank01Roster[$matchKey] ?? null;
        $tank01Id = $tank01Player ? (string) ($tank01Player['athlete_id'] ?? '') : null;

        $existing = WnbaPlayer::query()
          ->where('espn_athlete_id', $espnId)
          ->orWhere('athlete_id', $espnId)
          ->when($tank01Id, fn ($q) => $q->orWhere('tank01_player_id', $tank01Id)->orWhere('athlete_id', $tank01Id))
          ->first();

        $payload = [
          'athlete_id' => $espnId,
          'espn_athlete_id' => $espnId,
          'tank01_player_id' => $tank01Id,
          'athlete_display_name' => $espnPlayer['athlete_display_name'] ?? $tank01Player['athlete_display_name'] ?? 'Unknown Player',
          'athlete_short_name' => $espnPlayer['athlete_short_name'] ?? $tank01Player['athlete_short_name'] ?? 'Unknown',
          'athlete_jersey' => $espnPlayer['athlete_jersey'] ?? $tank01Player['athlete_jersey'] ?? null,
          'athlete_headshot_href' => $espnPlayer['athlete_headshot_href'] ?? null,
          'athlete_position_name' => $espnPlayer['athlete_position_name'] ?? $tank01Player['athlete_position_name'] ?? null,
          'athlete_position_abbreviation' => $espnPlayer['athlete_position_abbreviation'] ?? $tank01Player['athlete_position_abbreviation'] ?? null,
        ];

        if ($existing && $existing->athlete_id !== $espnId) {
          $this->repointPlayerForeignKeys($existing->id, $espnId, $payload);
        } else {
          WnbaPlayer::updateOrCreate(['athlete_id' => $espnId], $payload);
        }

        $merged++;
      }
    }

    return $merged;
  }

  public function syncGameMappings(int $season): int
  {
    $espnSchedule = app(EspnWnbaProvider::class)->fetchSchedule($season);
    $tank01Schedule = $this->safeTank01Schedule($season);
    $tank01ByKey = [];

    foreach ($tank01Schedule as $game) {
      $key = $this->gameMatchKey(
        (string) ($game['game_date'] ?? ''),
        (string) ($game['home_team_abbreviation'] ?? ''),
        (string) ($game['away_team_abbreviation'] ?? ''),
      );
      if ($key !== '') {
        $tank01ByKey[$key] = $game;
      }
    }

    $merged = 0;

    foreach ($espnSchedule as $espnGame) {
      $espnId = (string) ($espnGame['game_id'] ?? '');
      if ($espnId === '') {
        continue;
      }

      $key = $this->gameMatchKey(
        (string) ($espnGame['game_date'] ?? ''),
        (string) ($espnGame['home_team_abbreviation'] ?? ''),
        (string) ($espnGame['away_team_abbreviation'] ?? ''),
      );
      $tank01Game = $tank01ByKey[$key] ?? null;
      $tank01Id = $tank01Game ? (string) ($tank01Game['game_id'] ?? '') : null;

      $existing = WnbaGame::query()
        ->where('espn_game_id', $espnId)
        ->orWhere('game_id', $espnId)
        ->when($tank01Id, fn ($q) => $q->orWhere('tank01_game_id', $tank01Id)->orWhere('game_id', $tank01Id))
        ->first();

      $payload = [
        'game_id' => $espnId,
        'espn_game_id' => $espnId,
        'tank01_game_id' => $tank01Id,
        'season' => $espnGame['season'] ?? $season,
        'season_type' => is_numeric($espnGame['season_type'] ?? null) ? (int) $espnGame['season_type'] : 2,
        'game_date' => $espnGame['game_date'] ?? null,
        'game_date_time' => $espnGame['game_date_time'] ?? $espnGame['game_date'] ?? now(),
        'status_name' => $espnGame['status_name'] ?? null,
        'status_type' => $espnGame['status_type'] ?? null,
      ];

      if ($existing && $existing->game_id !== $espnId) {
        $this->repointGameForeignKeys($existing->id, $espnId, $payload);
      } else {
        WnbaGame::updateOrCreate(['game_id' => $espnId], $payload);
      }

      $merged++;
    }

    return $merged;
  }

  /**
   * @param  array<string, mixed>  $record
   * @return array<string, mixed>
   */
  public function normalizeBoxScoreRecord(array $record, string $provider): array
  {
    $this->loadCaches();

    $provider = strtolower($provider);
    $athleteId = (string) ($record['athlete_id'] ?? '');

    if ($provider === 'tank01' && $this->looksLikeTank01PlayerId($athleteId)) {
      $record['tank01_player_id'] = $athleteId;
      $canonical = $this->playerByTank01[$athleteId] ?? null;
      if ($canonical) {
        $record['athlete_id'] = $canonical;
        $record['espn_athlete_id'] = $this->playerByEspn[$canonical] ?? $canonical;
      }
    } elseif ($provider === 'espn' && $this->looksLikeEspnPlayerId($athleteId)) {
      $record['espn_athlete_id'] = $athleteId;
    }

    if (! empty($record['team_id'])) {
      $record['team_id'] = $this->resolveTeamId((string) $record['team_id'], $provider);
    }

    if (! empty($record['opponent_team_id'])) {
      $record['opponent_team_id'] = $this->resolveTeamId((string) $record['opponent_team_id'], $provider);
    }

    if (! empty($record['game_id'])) {
      $record = $this->normalizeGameOnRecord($record, $provider);
    }

    return $record;
  }

  /**
   * @param  array<string, mixed>  $record
   * @return array<string, mixed>
   */
  public function normalizeScheduleRecord(array $record, string $provider): array
  {
    $this->loadCaches();
    $provider = strtolower($provider);

    foreach (['home_team_id', 'away_team_id'] as $field) {
      if (! empty($record[$field])) {
        $record[$field] = $this->resolveTeamId((string) $record[$field], $provider);
      }
    }

    return $this->normalizeGameOnRecord($record, $provider);
  }

  public function resolveTeamId(string $externalId, string $provider): string
  {
    $this->loadCaches();
    $provider = strtolower($provider);

    if ($provider === 'tank01') {
      return $this->teamByTank01[$externalId]
        ?? $this->teamByAbv[strtoupper($externalId)]
        ?? $externalId;
    }

    return $this->teamByEspn[$externalId] ?? $externalId;
  }

  public function resolveGameId(string $externalId, string $provider): string
  {
    $this->loadCaches();
    $provider = strtolower($provider);

    if ($provider === 'tank01') {
      return $this->gameByTank01[$externalId] ?? $externalId;
    }

    return $this->gameByEspn[$externalId] ?? $externalId;
  }

  public function looksLikeEspnPlayerId(string $id): bool
  {
    return ctype_digit($id) && strlen($id) >= 7;
  }

  public function looksLikeTank01PlayerId(string $id): bool
  {
    return ctype_digit($id) && strlen($id) <= 6;
  }

  public function looksLikeEspnTeamId(string $id): bool
  {
    return ctype_digit($id) && strlen($id) >= 2 && strlen($id) <= 4;
  }

  public function looksLikeTank01TeamId(string $id): bool
  {
    return ctype_digit($id) && strlen($id) === 1;
  }

  public function looksLikeEspnGameId(string $id): bool
  {
    return ctype_digit($id) && strlen($id) >= 9;
  }

  public function looksLikeTank01GameId(string $id): bool
  {
    return str_contains($id, '@');
  }

  private function playerMatchKey(string $name, string $jersey, string $teamAbv): string
  {
    return $teamAbv.'|'.$jersey.'|'.$this->normalizeName($name);
  }

  private function gameMatchKey(string $date, string $homeAbv, string $awayAbv): string
  {
    $date = substr($date, 0, 10);
    $homeAbv = TeamCatalog::canonicalAbbreviation($homeAbv);
    $awayAbv = TeamCatalog::canonicalAbbreviation($awayAbv);

    return $date.'|'.$homeAbv.'|'.$awayAbv;
  }

  private function normalizeName(string $name): string
  {
    return Str::of($name)->lower()->ascii()->replaceMatches('/[^a-z0-9]/', '')->toString();
  }

  /**
   * @param  array<string, mixed>  $record
   * @return array<string, mixed>
   */
  private function normalizeGameOnRecord(array $record, string $provider): array
  {
    $externalGameId = (string) ($record['game_id'] ?? '');
    $canonical = $this->resolveGameId($externalGameId, $provider);

    if ($provider === 'tank01' && $this->looksLikeTank01GameId($externalGameId)) {
      $record['tank01_game_id'] = $externalGameId;
      $record['espn_game_id'] = $canonical !== $externalGameId ? $canonical : ($record['espn_game_id'] ?? null);
      $record['game_id'] = $canonical !== $externalGameId ? $canonical : $externalGameId;
    } elseif ($provider === 'espn' && $this->looksLikeEspnGameId($externalGameId)) {
      $record['espn_game_id'] = $externalGameId;
      $record['game_id'] = $canonical;
    }

    return $record;
  }

  /**
   * @param  array<string, mixed>  $payload
   */
  private function repointPlayerForeignKeys(int $playerRowId, string $canonicalAthleteId, array $payload): void
  {
    $existing = WnbaPlayer::find($playerRowId);
    if (! $existing) {
      return;
    }

    $target = WnbaPlayer::where('athlete_id', $canonicalAthleteId)->first();

    if ($target && $target->id !== $existing->id) {
      \App\Models\WnbaPlayerGame::where('player_id', $existing->id)->update(['player_id' => $target->id]);
      \App\Models\WnbaPlay::where('player_id', $existing->id)->update(['player_id' => $target->id]);
      $existing->delete();
      $target->update($payload);
    } else {
      $existing->update(array_merge($payload, ['athlete_id' => $canonicalAthleteId]));
    }
  }

  private function repointTeamForeignKeys(string $fromTeamId, string $toTeamId): void
  {
    if ($fromTeamId === $toTeamId) {
      return;
    }

    \App\Models\WnbaGameTeam::where('team_id', $fromTeamId)->update(['team_id' => $toTeamId]);
    \App\Models\WnbaGameTeam::where('opponent_team_id', $fromTeamId)->update(['opponent_team_id' => $toTeamId]);
    \App\Models\WnbaPlayerGame::where('team_id', $fromTeamId)->update(['team_id' => $toTeamId]);
    \App\Models\WnbaPlay::where('team_id', $fromTeamId)->update(['team_id' => $toTeamId]);
    \App\Models\WnbaPlay::where('score_team_id', $fromTeamId)->update(['score_team_id' => $toTeamId]);
  }

  /**
   * @param  array<string, mixed>  $payload
   */
  private function repointGameForeignKeys(int $gameRowId, string $canonicalGameId, array $payload): void
  {
    $existing = WnbaGame::find($gameRowId);
    if (! $existing) {
      return;
    }

    $target = WnbaGame::where('game_id', $canonicalGameId)->first();

    if ($target && $target->id !== $existing->id) {
      \App\Models\WnbaPlayerGame::where('game_id', $existing->id)->update(['game_id' => $target->id]);
      \App\Models\WnbaGameTeam::where('game_id', $existing->id)->update(['game_id' => $target->id]);
      \App\Models\WnbaPlay::where('game_id', $existing->id)->update(['game_id' => $target->id]);
      $existing->delete();
      $target->update($payload);
    } else {
      $existing->update(array_merge($payload, ['game_id' => $canonicalGameId]));
    }
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function safeTank01Teams(?int $season = null): array
  {
    try {
      return app(Tank01WnbaProvider::class)->fetchTeams($season ?? (int) config('wnba.seasons.current_season'));
    } catch (\Throwable $e) {
      Log::warning('Tank01 teams unavailable for identity sync', ['error' => $e->getMessage()]);

      return [];
    }
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function safeTank01RosterPlayers(): array
  {
    try {
      return app(Tank01WnbaProvider::class)->fetchRosterPlayers();
    } catch (\Throwable $e) {
      Log::warning('Tank01 roster unavailable for identity sync', ['error' => $e->getMessage()]);

      return [];
    }
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function safeTank01Schedule(int $season): array
  {
    try {
      return app(Tank01WnbaProvider::class)->fetchSchedule($season);
    } catch (\Throwable $e) {
      Log::warning('Tank01 schedule unavailable for identity sync', ['error' => $e->getMessage()]);

      return [];
    }
  }
}
