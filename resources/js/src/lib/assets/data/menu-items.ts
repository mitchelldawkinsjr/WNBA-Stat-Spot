import type {MenuItemType} from "$lib/types/menu";

export const MENU_ITEMS: MenuItemType[] = [
    {
        key: 'wnba',
        label: 'WNBA ANALYTICS',
        isTitle: true,
    },
    {
        key: 'dashboard',
        icon: 'iconamoon:home-duotone',
        label: 'Dashboard',
        url: '/',
    },
    {
        key: 'predictions-section',
        label: 'AI PREDICTIONS',
        isTitle: true,
    },
    {
        key: 'predictions',
        icon: 'iconamoon:crystal-ball-duotone',
        label: 'Prediction Engine',
        url: '/reports/predictions',
    },
    {
        key: 'todays-props',
        icon: 'iconamoon:fire-duotone',
        label: 'Today\'s Best Props',
        url: '/reports/todays-props',
    },
    {
        key: 'prop-scanner',
        icon: 'iconamoon:radar-duotone',
        label: 'Prop Scanner',
        url: '/advanced/prop-scanner',
    },
    {
        key: 'live-odds',
        icon: 'iconamoon:lightning-duotone',
        label: 'Live Odds',
        url: '/advanced/live-odds',
    },
    {
        key: 'data-overview',
        label: 'DATA',
        isTitle: true,
    },
    {
        key: 'teams',
        icon: 'iconamoon:users-duotone',
        label: 'Teams',
        url: '/teams',
    },
    {
        key: 'players',
        icon: 'iconamoon:user-duotone',
        label: 'Players',
        url: '/players',
    },
    {
        key: 'compare-players',
        icon: 'iconamoon:compare-duotone',
        label: 'Compare Players',
        url: '/compare/players',
    },
    {
        key: 'games',
        icon: 'iconamoon:game-duotone',
        label: 'Games',
        url: '/games',
    },
    {
        key: 'stats',
        icon: 'iconamoon:chart-duotone',
        label: 'Statistics',
        url: '/stats',
    },
    {
        key: 'analytics-reports',
        label: 'ANALYTICS',
        isTitle: true,
    },
    {
        key: 'reports',
        icon: 'iconamoon:file-document-duotone',
        label: 'Reports',
        children: [
            {
                key: 'player-reports',
                label: 'Player Reports',
                url: '/reports/players',
                parentKey: 'reports',
            },
            {
                key: 'team-reports',
                label: 'Team Reports',
                url: '/reports/teams',
                parentKey: 'reports',
            },
        ],
    },
    {
        key: 'advanced-tools',
        label: 'ADVANCED TOOLS',
        isTitle: true,
    },
    {
        key: 'model-validation',
        icon: 'iconamoon:shield-check-duotone',
        label: 'Model Validation',
        url: '/advanced/model-validation',
    },
    {
        key: 'prediction-testing',
        icon: 'iconamoon:history-duotone',
        label: 'Historical Testing',
        url: '/advanced/prediction-testing',
    },
    {
        key: 'data-management',
        icon: 'iconamoon:database-duotone',
        label: 'Data Management',
        url: '/advanced/data-management',
    },
    {
        key: 'system-section',
        label: 'SYSTEM',
        isTitle: true,
    },
    {
        key: 'methodology',
        icon: 'iconamoon:book-duotone',
        label: 'Methodology',
        children: [
            {
                key: 'methodology-overview',
                label: 'Overview',
                url: '/methodology',
                parentKey: 'methodology',
            },
            {
                key: 'prop-analysis',
                label: 'Prop Analysis Methods',
                url: '/methodology/prop-analysis',
                parentKey: 'methodology',
            },
        ],
    },
    {
        key: 'advanced-overview',
        icon: 'iconamoon:3d-duotone',
        label: 'Advanced Overview',
        url: '/advanced',
    },
];
