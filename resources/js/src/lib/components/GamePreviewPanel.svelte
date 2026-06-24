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

<div class="game-preview-panel">
{#if preview.prediction}
    <div class="card mb-4 border-primary game-preview-panel__card">
        <div class="card-body">
            <div class="game-preview-panel__prediction-header">
                <div class="game-preview-panel__prediction-copy">
                    <p class="ds-section-label mb-1.5">Data-Driven Pick</p>
                    <h2 class="ds-headline-md mb-1">
                        {preview.prediction.predicted_winner_label}
                        <span class="text-muted fs-6 fw-normal">to win</span>
                    </h2>
                    <p class="ds-text-muted mb-0">{preview.analysis.summary}</p>
                </div>
                <div class="game-preview-panel__prediction-badges">
                    <div class="badge bg-primary-subtle text-primary-emphasis px-3 py-2">
                        {preview.prediction.confidence}% confidence
                    </div>
                    <p class="ds-text-muted small mb-0 mt-2">
                        Spread: {preview.prediction.projected_spread > 0 ? '+' : ''}{preview.prediction.projected_spread}
                    </p>
                </div>
            </div>

            <div class="game-preview-panel__prob-labels">
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

            <div class="game-preview-panel__metrics">
                <div class="ds-score-card game-preview-panel__metric">
                    <p class="ds-section-label mb-0">Projected</p>
                    <p class="mb-0 fw-bold">{awayAbbr} {preview.prediction.projected_score.away}</p>
                    <p class="mb-0 fw-bold text-primary">{homeAbbr} {preview.prediction.projected_score.home}</p>
                </div>
                <div class="ds-score-card game-preview-panel__metric">
                    <p class="ds-section-label mb-0">Total</p>
                    <p class="mb-0 fw-bold fs-5">{preview.prediction.projected_score.total}</p>
                </div>
                <div class="ds-score-card game-preview-panel__metric">
                    <p class="ds-section-label mb-0">Pace</p>
                    <p class="mb-0 fw-bold fs-5">{preview.prediction.projected_pace}</p>
                </div>
            </div>
        </div>
    </div>
{/if}

{#if preview.analysis?.bullets?.length}
    <div class="card mb-4 game-preview-panel__card">
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

<div class="game-preview-panel__split mb-4">
    <div class="game-preview-panel__card card">
        <div class="card-header border-0">
            <h3 class="ds-headline-sm mb-0">Team Comparison</h3>
        </div>
        <div class="card-body game-preview-panel__chart-wrap">
            <TeamMatchupRadarChart
                labels={preview.comparison.radar.labels}
                homeData={preview.comparison.radar.home}
                awayData={preview.comparison.radar.away}
                homeLabel={homeAbbr}
                awayLabel={awayAbbr}
            />
        </div>
    </div>
    <div class="game-preview-panel__card card">
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

<div class="card mb-4 game-preview-panel__card">
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
    <div class="card mb-4 game-preview-panel__card">
        <div class="card-header border-0 game-preview-panel__card-header">
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

<div class="game-preview-panel__teams mb-4">
    {#each [preview.away_team, preview.home_team] as team}
        <div class="game-preview-panel__card card">
            <div class="card-header border-0 game-preview-panel__team-header">
                {#if team.logo}
                    <img src={team.logo} alt={team.abbreviation} width="28" height="28" class="game-preview-panel__team-logo" />
                {/if}
                <div class="game-preview-panel__team-title">
                    <h3 class="ds-headline-sm mb-0">{team.name}</h3>
                    <p class="ds-text-muted small mb-0">
                        {team.record.wins}-{team.record.losses}
                        · {team.is_home ? 'Home' : 'Away'} split:
                        {team.context_split?.wins ?? 0}-{team.context_split?.losses ?? 0}
                    </p>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="game-preview-panel__mini-stats">
                    <div class="ds-score-card game-preview-panel__mini-stat">
                        <p class="ds-section-label mb-0">PPG</p>
                        <p class="mb-0 fw-bold">{team.season_stats.points_per_game ?? '–'}</p>
                    </div>
                    <div class="ds-score-card game-preview-panel__mini-stat">
                        <p class="ds-section-label mb-0">Opp PPG</p>
                        <p class="mb-0 fw-bold">{team.season_stats.points_allowed_per_game ?? '–'}</p>
                    </div>
                    <div class="ds-score-card game-preview-panel__mini-stat">
                        <p class="ds-section-label mb-0">Net Rtg</p>
                        <p class="mb-0 fw-bold">{team.efficiency?.net_rating ?? '–'}</p>
                    </div>
                </div>

                <div class="game-preview-panel__chart-wrap">
                    <TeamGameResultsChart
                        data={team.recent_games}
                        height="260px"
                    />
                </div>

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
                                        <div class="game-preview-panel__player-cell">
                                            {#if player.headshot}
                                                <img src={player.headshot} alt="" width="24" height="24" class="rounded-circle game-preview-panel__player-photo" />
                                            {/if}
                                            <div class="game-preview-panel__player-name">
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
    {/each}
</div>
</div>

<style>
    .game-preview-panel {
        display: flex;
        flex-direction: column;
        gap: 0;
        min-width: 0;
        max-width: 100%;
    }

    .game-preview-panel__card {
        min-width: 0;
        max-width: 100%;
        overflow: hidden;
    }

    .game-preview-panel__prediction-header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem 1rem;
        margin-bottom: 0.75rem;
    }

    .game-preview-panel__prediction-copy {
        flex: 1 1 16rem;
        min-width: 0;
    }

    .game-preview-panel__prediction-badges {
        flex: 0 1 auto;
        min-width: 0;
        text-align: right;
    }

    .game-preview-panel__prob-labels {
        display: flex;
        justify-content: space-between;
        gap: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .game-preview-panel__metrics {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .game-preview-panel__metric {
        flex: 1 1 7rem;
        min-width: 0;
        padding: 0.5rem;
        text-align: center;
    }

    .game-preview-panel__split {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: stretch;
    }

    .game-preview-panel__split > .game-preview-panel__card {
        flex: 1 1 18rem;
    }

    .game-preview-panel__teams {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: stretch;
    }

    .game-preview-panel__teams > .game-preview-panel__card {
        flex: 1 1 20rem;
    }

    .game-preview-panel__card-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
    }

    .game-preview-panel__team-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
    }

    .game-preview-panel__team-logo {
        flex-shrink: 0;
        object-fit: contain;
    }

    .game-preview-panel__team-title {
        min-width: 0;
        overflow: hidden;
    }

    .game-preview-panel__team-title h3 {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .game-preview-panel__mini-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .game-preview-panel__mini-stat {
        flex: 1 1 5.5rem;
        min-width: 0;
        padding: 0.5rem;
        text-align: center;
    }

    .game-preview-panel__chart-wrap {
        min-width: 0;
        max-width: 100%;
        overflow: hidden;
    }

    .game-preview-panel__player-cell {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
    }

    .game-preview-panel__player-photo {
        flex-shrink: 0;
        object-fit: cover;
    }

    .game-preview-panel__player-name {
        min-width: 0;
        overflow: hidden;
    }

    .game-preview-panel__player-name a {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .win-prob-bar {
        display: flex;
        width: 100%;
        min-width: 0;
        height: 10px;
        border-radius: 999px;
        overflow: hidden;
        background: #e9ecef;
    }

    .win-prob-away {
        flex: 0 0 auto;
        background: #3491fc;
        transition: width 0.3s ease;
    }

    .win-prob-home {
        flex: 0 0 auto;
        background: #ff6c2f;
        transition: width 0.3s ease;
    }
</style>
