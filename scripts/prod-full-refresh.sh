#!/bin/bash
# Full production WNBA agent refresh (data backfill → entity → analytics), timed.
set -uo pipefail

LOG=/tmp/wnba_full_refresh.log
REPORT=/tmp/wnba_full_refresh_report.json
TIMES=/tmp/wnba_step_times.txt

exec > >(tee -a "$LOG") 2>&1

echo "===== WNBA FULL PROD REFRESH START $(date -u +%Y-%m-%dT%H:%M:%SZ) ====="
START_ALL=$(date +%s)
rm -f "$TIMES"
touch "$TIMES"

run_step() {
  local name="$1"
  shift
  local start end elapsed code
  echo ""
  echo "----- STEP: $name -----"
  echo "CMD: $*"
  start=$(date +%s)
  set +e
  "$@"
  code=$?
  set -e
  end=$(date +%s)
  elapsed=$((end - start))
  echo "----- END $name: exit=$code elapsed=${elapsed}s -----"
  echo "${name}|${elapsed}|${code}" >> "$TIMES"
  return $code
}

ID_CODE=0
DATA_CODE=0
ENTITY_CODE=0
ANALYTICS_CODE=0

run_step identity_sync docker exec wnba-stat-spot-app php artisan app:sync-entity-identities --season=2026 || ID_CODE=$?
run_step data_backfill docker exec wnba-stat-spot-app php artisan app:wnba-agent data --mode=backfill --season=2026 --no-chain || DATA_CODE=$?
run_step entity_audit docker exec wnba-stat-spot-app php artisan app:wnba-agent entity --mode=audit --season=2026 || ENTITY_CODE=$?
run_step analytics_recompute docker exec wnba-stat-spot-app php artisan app:wnba-agent analytics --season=2026 || ANALYTICS_CODE=$?

END_ALL=$(date +%s)
TOTAL=$((END_ALL - START_ALL))
echo ""
echo "===== WNBA FULL PROD REFRESH DONE $(date -u +%Y-%m-%dT%H:%M:%SZ) total=${TOTAL}s ====="
echo "exit codes: identity=$ID_CODE data=$DATA_CODE entity=$ENTITY_CODE analytics=$ANALYTICS_CODE"

docker exec wnba-stat-spot-app php -r '
require "/var/www/html/vendor/autoload.php";
$app = require "/var/www/html/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$season = 2026;
$runs = DB::table("wnba_agent_runs")->orderByDesc("id")->limit(6)->get([
    "id","agent","mode","status","season","started_at","completed_at","counters","warnings","errors"
]);
$finals = DB::table("wnba_games")->where("season",$season)->where("status_name","STATUS_FINAL")->count();
$finalsWithPg = DB::select("select count(distinct g.id)::int as c from wnba_games g join wnba_player_games pg on g.id=pg.game_id where g.season=? and g.status_name=?", [$season,"STATUS_FINAL"])[0]->c;
echo json_encode([
    "runs" => $runs,
    "odds" => DB::table("wnba_odds_snapshots")->count(),
    "injuries" => DB::table("wnba_injury_reports")->count(),
    "raw_payloads" => DB::table("wnba_raw_payloads")->count(),
    "open_review" => DB::table("wnba_data_conflicts")->where("requires_review",true)->whereNull("resolved_at")->count(),
    "player_season_stats" => DB::table("wnba_player_season_stats")->where("season",$season)->count(),
    "team_season_stats" => DB::table("wnba_team_season_stats")->where("season",$season)->count(),
    "matchups" => DB::table("wnba_matchup_summaries")->where("season",$season)->count(),
    "power_rankings" => DB::table("wnba_team_power_rankings")->where("season",$season)->count(),
    "insights" => DB::table("wnba_daily_insights")->where("season",$season)->count(),
    "plays" => DB::table("wnba_plays")->count(),
    "finals" => $finals,
    "finals_with_player_games" => $finalsWithPg,
], JSON_PRETTY_PRINT);
' > /tmp/wnba_post_refresh_audit.json

python3 - << PY
import json
from datetime import datetime, timezone
steps = []
for line in open("/tmp/wnba_step_times.txt"):
    name, elapsed, code = line.strip().split("|")
    steps.append({"step": name, "elapsed_sec": int(elapsed), "exit_code": int(code)})
report = {
    "finished_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
    "total_sec": sum(s["elapsed_sec"] for s in steps),
    "steps": steps,
    "exit_codes": {
        "identity": ${ID_CODE},
        "data": ${DATA_CODE},
        "entity": ${ENTITY_CODE},
        "analytics": ${ANALYTICS_CODE},
    },
}
try:
    report["post_audit"] = json.load(open("/tmp/wnba_post_refresh_audit.json"))
except Exception as e:
    report["post_audit_error"] = str(e)
json.dump(report, open("/tmp/wnba_full_refresh_report.json", "w"), indent=2)
print(json.dumps({k: report[k] for k in ("finished_at", "total_sec", "steps", "exit_codes")}, indent=2))
PY

exit $(( ID_CODE || DATA_CODE || ENTITY_CODE || ANALYTICS_CODE ))
