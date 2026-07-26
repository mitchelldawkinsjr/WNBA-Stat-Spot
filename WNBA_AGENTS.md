# WNBA Agents — Operations Guide

Three deterministic in-app pipelines ("agents") keep the data reliable. They
run on production via the Laravel scheduler and queue worker — no external
process needed.

| Agent | What it does | Trigger |
|---|---|---|
| **Data** | Ingests schedule, team/player box scores, play-by-play, injuries, odds. Stores raw payloads, lineage, validation status; resolves cross-source conflicts by priority. | Nightly 02:00 (scheduler), `app:wnba-agent data`, `POST /api/wnba/data/agent-run` |
| **Entity Integrity** | Audits teams/players/games: orphans, duplicates, ID-mapping gaps, metadata problems. Writes findings to the review queue. | Chained after every data run; weekly full audit Mon 03:00 |
| **Analytics** | Precomputes player/team season stats, per-game advanced stats, and matchup summaries into aggregate tables the API reads. | Chained after the entity audit; `app:wnba-agent analytics` |

Nightly chain: **data → entity (audit) → analytics → cache clear**. The
evening Tank01 live sync (`app:sync-wnba-live`) also records agent runs with
mode `live`.

## CLI

```bash
php artisan app:wnba-agent data                        # inline incremental ingest + chained follow-ups
php artisan app:wnba-agent data --queue                # dispatch to queue instead
php artisan app:wnba-agent data --mode=backfill --no-chain
php artisan app:wnba-agent analytics --season=2026
php artisan app:wnba-agent entity --mode=audit --season=all
php artisan app:wnba-agent data --dry-run              # report without writing
```

## API (admin)

```
POST /api/wnba/data/agent-run                {agent, mode?, season?, dry_run?}
GET  /api/wnba/data/agent-runs               ?agent=&limit=
GET  /api/wnba/data/agent-runs/{id|uuid}
GET  /api/wnba/data/review-queue             ?entity_type=&limit=
POST /api/wnba/data/review-queue/{id}/resolve {resolution_reason, selected_value?}
```

## Key tables

- `wnba_agent_runs` — structured summary of every run (status, counters, warnings, errors)
- `wnba_raw_payloads` — untouched provider responses (sha256-deduped, pruned after `WNBA_RAW_PAYLOAD_RETENTION_DAYS`)
- `wnba_data_conflicts` — cross-source disagreements + integrity findings; `requires_review=true` rows are the human review queue
- `wnba_injury_reports`, `wnba_odds_snapshots` — append-only history for grading predictions
- `wnba_player_season_stats`, `wnba_team_season_stats`, `wnba_player_game_advanced`, `wnba_matchup_summaries` — Analytics Agent output; API controllers read these (with on-the-fly fallback until first run)
- Lineage columns on `wnba_games`, `wnba_game_teams`, `wnba_player_games`, `wnba_plays`: `source_id`, `raw_payload_id`, `ingested_at`, `validation_status`

## Production rollout

```bash
ssh vps
cd /path/to/app

# 1. Deploy code, then run migrations (adds the new tables + columns; no data rewrites)
php artisan migrate --force

# 2. Restart the queue worker so the new job classes load
php artisan queue:restart

# 3. First runs (order matters: data → entity → analytics happen automatically via chain)
php artisan app:wnba-agent data --queue

# 4. Verify
php artisan tinker --execute="App\Models\WnbaAgentRun::latest()->take(5)->get(['agent','mode','status','counters'])->each(fn(\$r) => print_r(\$r->toArray()));"
curl -s localhost/api/wnba/data/agent-runs | jq '.data[0]'
curl -s localhost/api/wnba/data/review-queue | jq '.data.count'
```

Scheduler entries are already registered in `routes/console.php`; the existing
cron (`php artisan schedule:run`) picks them up automatically. The old
`app:import-wnba-data` command remains available for manual imports but is no
longer scheduled — the data agent wraps the same pipeline with lineage,
validation, and conflict handling on top.

## Behavior notes

- **PBP is on by default** for the nightly run (SportsDataverse CSVs, no API
  quota). Disable with `WNBA_PBP_DEFAULT=false` or `--no-pbp` if memory is
  tight; `WNBA_IMPORT_MEMORY_LIMIT` (default 512M) applies.
- **Source priority** (`config/wnba.php` → `agents.source_priority`):
  espn > tank01 > sportsdataverse > sportsblaze. A lower-priority source never
  silently overwrites a higher-priority value; disagreements land in
  `wnba_data_conflicts` (counting-stat gaps > 5 require human review).
- **Invalid box rows** (impossible stat math) are stored with
  `validation_status='invalid'` and excluded from aggregates and leaders.
- Rules for future development live in `.cursor/rules/wnba-data-agent.mdc`,
  `wnba-analytics-agent.mdc`, and `wnba-entity-agent.mdc`.
