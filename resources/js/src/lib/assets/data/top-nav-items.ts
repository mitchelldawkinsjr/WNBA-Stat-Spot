export type TopNavItem = {
    key: string;
    label: string;
    url: string;
};

/** Primary routes shown in the top bar (Stitch shell). Full menu remains in sidebar. */
export const TOP_NAV_ITEMS: TopNavItem[] = [
    { key: 'dashboard', label: 'Home', url: '/' },
    { key: 'predictions', label: 'Predictions', url: '/reports/predictions' },
    { key: 'todays-props', label: "Today's Props", url: '/reports/todays-props' },
    { key: 'players', label: 'Players', url: '/players' },
    { key: 'teams', label: 'Teams', url: '/teams' },
    { key: 'games', label: 'Games', url: '/games' },
    { key: 'stats', label: 'Stats', url: '/stats' },
    { key: 'prop-scanner', label: 'Scanner', url: '/advanced/prop-scanner' },
    { key: 'live-odds', label: 'Live Odds', url: '/advanced/live-odds' },
];
