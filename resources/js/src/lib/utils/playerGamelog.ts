export interface ProfileGameRow {
    id: number;
    game_id: number;
    minutes: string | null;
    field_goals_made: number;
    field_goals_attempted: number;
    three_point_field_goals_made: number;
    three_point_field_goals_attempted: number;
    free_throws_made: number;
    free_throws_attempted: number;
    rebounds: number;
    assists: number;
    steals: number;
    blocks: number;
    turnovers: number;
    fouls: number;
    points: number;
    starter: boolean;
    did_not_play: boolean;
    team?: {
        team_display_name: string;
        team_abbreviation: string;
        team_logo: string;
    };
    game?: {
        game_date: string;
        season: string;
    };
}

export function mapGamelogToPlayerGame(row: Record<string, unknown>, season: number): ProfileGameRow {
    const minutes = row.minutes;
    const oppAbbr = String(row.opponent_team_abbreviation ?? 'OPP');
    const homeAway = row.home_away === 'away' ? '@' : 'vs';
    return {
        id: 0,
        game_id: Number(row.game_id) || 0,
        minutes: minutes != null ? String(minutes) : null,
        field_goals_made: Number(row.field_goals_made) || 0,
        field_goals_attempted: Number(row.field_goals_attempted) || 0,
        three_point_field_goals_made: Number(row.three_point_field_goals_made) || 0,
        three_point_field_goals_attempted: Number(row.three_point_field_goals_attempted) || 0,
        free_throws_made: Number(row.free_throws_made) || 0,
        free_throws_attempted: Number(row.free_throws_attempted) || 0,
        rebounds: Number(row.rebounds) || 0,
        assists: Number(row.assists) || 0,
        steals: Number(row.steals) || 0,
        blocks: Number(row.blocks) || 0,
        turnovers: Number(row.turnovers) || 0,
        fouls: Number(row.fouls) || 0,
        points: Number(row.points) || 0,
        starter: false,
        did_not_play: false,
        team: {
            team_display_name: String(row.opponent_team_name ?? 'Opponent'),
            team_abbreviation: `${homeAway} ${oppAbbr}`,
            team_logo: '',
        },
        game: {
            game_date: String(row.game_date ?? ''),
            season: String(season),
        },
    };
}

export function computeAverages(games: ProfileGameRow[]) {
    if (games.length === 0) return null;
    const sum = (fn: (g: ProfileGameRow) => number) => games.reduce((acc, g) => acc + fn(g), 0);
    const fga = sum((g) => g.field_goals_attempted);
    const fgm = sum((g) => g.field_goals_made);
    return {
        points: (sum((g) => g.points) / games.length).toFixed(1),
        rebounds: (sum((g) => g.rebounds) / games.length).toFixed(1),
        assists: (sum((g) => g.assists) / games.length).toFixed(1),
        steals: (sum((g) => g.steals) / games.length).toFixed(1),
        blocks: (sum((g) => g.blocks) / games.length).toFixed(1),
        fg_percentage: fga > 0 ? ((fgm / fga) * 100).toFixed(1) : '0.0',
        games: games.length,
    };
}
