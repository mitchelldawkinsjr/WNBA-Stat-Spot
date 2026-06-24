<script lang="ts">
    import type { GamePreview } from '$lib/api/client';
    import TeamMatchupRadarChart from '$lib/components/charts/TeamMatchupRadarChart.svelte';
    import TeamGameResultsChart from '$lib/components/charts/TeamGameResultsChart.svelte';

    export let preview: GamePreview;

    function formatPct(value: number): string {
        return `${Math.round(value)}%`;
    }

    function trendClass(trend: string): string {
        if (trend === 'up' || trend === 'increasing') return 'text-success';
        if (trend === 'down' || trend === 'decreasing') return 'text-danger';
        return 'text-muted';
    }

    $: homeAbbr = preview.home_team?.abbreviation ?? 'HOME';
    $: awayAbbr = preview.away_team?.abbreviation ?? 'AWAY';
</script>

{#if preview.prediction}
    <div class="card mb-4 border-primary">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <p class="ds-section-label mb-1.5">Data-Driven Pick</p>
                    <h2 class="ds-headline-md mb-1">
                        {preview.prediction.predicted_winner_label}
                        <span class="text-muted fs-6 fw-normal">to win</span>
                    </h2>
                    <p class="ds-text-muted mb-0">{preview.analysis.summary}</p>
                </div>
                <div class="text-end">
                    <div class="badge bg-primary-subtle text-primary-emphasis px-3 py-2">
                        {preview.prediction.confidence}% confidence
                    </div>
                    <p class="ds-text-muted small mb-0 mt-2">
                        Spread: {preview.prediction.projected_spread > 0 ? '+' : ''}{preview.prediction.projected_spread}
                    </p>
                </div>
            </div>

            <div class="mb-2 d-flex justify-content-between small fw-semibold">
                <span>{awayAbbr} {formatPct(preview.prediction.win_probability.away)}</span>
                <span>{homeAbbr} {formatPct(preview.prediction.win_probability.home)}</span>
            </div>
            <div class="win-prob-bar mb-3">
                <div
                    class="win-prob-away"
                    style="width: {preview.prediction.win_probability.away}%"
                ></div>
                <div
                    class="win-prob-home"
                    style="width: {preview.prediction.win_probability.home}%"
                ></div>
            </div>

            <div class="row g-2 text-center">
                <div class="col-4">
                    <div class="ds-score-card py-2">
                        <p class="ds-section-label mb-0">Projected</p>
                        <p class="mb-0 fw-bold">{awayAbbr} {preview.prediction.projected_score.away}</p>
                        <p class="mb-0 fw-bold text-primary">{homeAbbr} {preview.prediction.projected_score.home}</p>
                    </div>
                </div>
                <div class="col-4">
                    <div class="ds-score-card py-2">
                        <p class="ds-section-label mb-0">Total</p>
                        <p class="mb-0 fw-bold fs-5">{preview.prediction.projected_score.total}</p>
                    </div>
                </div>
                <div class="col-4">
                    <div class="ds-score-card py-2">
                        <p class="ds-section-label mb-0">Pace</p>
                        <p class="mb-0 fw-bold fs-5">{preview.prediction.projected_pace}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}

{#if preview.analysis?.bullets?.length}
    <div class="card mb-4">
        <div class="card-header border-0">
            <h3 class="ds-headline-sm mb-0">Game Preview</h3>
        </div>
        <div class="card-body pt-0">
            <ul class="mb-0 ps-3">
                {#each preview.analysis.bullets as bullet}
                    <li class="mb-2">{bullet}</li>
                {/each}
            </ul>
        </div>
    </div>
{/if}

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header border-0">
                <h3 class="ds-headline-sm mb-0">Team Comparison</h3>
            </div>
            <div class="card-body">
                <TeamMatchupRadarChart
                    labels={preview.comparison.radar.labels}
                    homeData={preview.comparison.radar.home}
                    awayData={preview.comparison.radar.away}
                    homeLabel={homeAbbr}
                    awayLabel={awayAbbr}
                />
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header border-0">
                <h3 class="ds-headline-sm mb-0">Key Factors</h3>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Factor</th>
                                <th>Edge</th>
                                <th>Favors</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each preview.prediction?.factors ?? [] as factor}
                                <tr>
                                    <td>{factor.factor}</td>
                                    <td>{factor.edge > 0 ? '+' : ''}{factor.edge}</td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            {factor.favors === 'home' ? homeAbbr : factor.favors === 'away' ? awayAbbr : 'Even'}
                                        </span>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header border-0">
        <h3 class="ds-headline-sm mb-0">Season Stats Comparison</h3>
    </div>
    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>Stat</th>
                        <th class="text-end">{awayAbbr}</th>
                        <th class="text-end">{homeAbbr}</th>
                    </tr>
                </thead>
                <tbody>
                    {#each preview.comparison.table as row}
                        <tr>
                            <td>{row.stat}</td>
                            <td class="text-end">{row.away}</td>
                            <td class="text-end fw-semibold">{row.home}</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>
</div>

{#if preview.head_to_head?.total_games > 0}
    <div class="card mb-4">
        <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="ds-headline-sm mb-0">Head-to-Head</h3>
            <span class="badge bg-secondary-subtle text-secondary-emphasis">
                {homeAbbr} {preview.head_to_head.home_team_wins}-{preview.head_to_head.away_team_wins} {awayAbbr}
            </span>
        </div>
        <div class="card-body pt-0">
            <p class="ds-text-muted small">
                Avg total {preview.head_to_head.avg_total_points} pts · Avg margin {preview.head_to_head.avg_margin > 0 ? '+' : ''}{preview.head_to_head.avg_margin} ({homeAbbr})
            </p>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Matchup</th>
                            <th>Score</th>
                            <th>Winner</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each preview.head_to_head.recent_meetings as meeting}
                            <tr>
                                <td>{meeting.date}</td>
                                <td>{awayAbbr} @ {homeAbbr}</td>
                                <td>{meeting.away_score} – {meeting.home_score}</td>
                                <td>
                                    <span class="badge {meeting.winner === 'home' ? 'bg-primary' : 'bg-secondary'}">
                                        {meeting.winner === 'home' ? homeAbbr : awayAbbr}
                                    </span>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
{/if}

<div class="row g-4 mb-4">
    {#each [preview.away_team, preview.home_team] as team}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header border-0 d-flex align-items-center gap-2">
                    {#if team.logo}
                        <img src={team.logo} alt={team.abbreviation} width="28" height="28" style="object-fit:contain;" />
                    {/if}
                    <div>
                        <h3 class="ds-headline-sm mb-0">{team.name}</h3>
                        <p class="ds-text-muted small mb-0">
                            {team.record.wins}-{team.record.losses}
                            · {team.is_home ? 'Home' : 'Away'} split:
                            {team.context_split?.wins ?? 0}-{team.context_split?.losses ?? 0}
                        </p>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="ds-score-card py-2 text-center">
                                <p class="ds-section-label mb-0">PPG</p>
                                <p class="mb-0 fw-bold">{team.season_stats.points_per_game ?? '–'}</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="ds-score-card py-2 text-center">
                                <p class="ds-section-label mb-0">Opp PPG</p>
                                <p class="mb-0 fw-bold">{team.season_stats.points_allowed_per_game ?? '–'}</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="ds-score-card py-2 text-center">
                                <p class="ds-section-label mb-0">Net Rtg</p>
                                <p class="mb-0 fw-bold">{team.efficiency?.net_rating ?? '–'}</p>
                            </div>
                        </div>
                    </div>

                    <TeamGameResultsChart
                        data={team.recent_games}
                        height="260px"
                    />

                    <h4 class="ds-headline-sm mt-4 mb-3">Key Players</h4>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Player</th>
                                    <th>Season</th>
                                    <th>L5</th>
                                    <th>vs Opp</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each team.key_players as player}
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                {#if player.headshot}
                                                    <img src={player.headshot} alt="" width="24" height="24" class="rounded-circle" style="object-fit:cover;" />
                                                {/if}
                                                <div>
                                                    <a href="/players/{player.player_id}" class="text-decoration-none fw-semibold">{player.name}</a>
                                                    <div class="small text-muted">{player.position}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{player.season.ppg}/{player.season.rpg}/{player.season.apg}</td>
                                        <td class={trendClass(player.last_5.trend)}>
                                            {player.last_5.ppg}/{player.last_5.rpg}/{player.last_5.apg}
                                        </td>
                                        <td>
                                            {#if player.vs_opponent.games > 0}
                                                {player.vs_opponent.ppg} <span class="text-muted">({player.vs_opponent.games}g)</span>
                                            {:else}
                                                <span class="text-muted">–</span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    {/each}
</div>

<style>
    .win-prob-bar {
        display: flex;
        height: 10px;
        border-radius: 999px;
        overflow: hidden;
        background: #e9ecef;
    }

    .win-prob-away {
        background: #3491fc;
        transition: width 0.3s ease;
    }

    .win-prob-home {
        background: #ff6c2f;
        transition: width 0.3s ease;
    }
</style>
