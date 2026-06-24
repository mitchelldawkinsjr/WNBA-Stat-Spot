import {persisted} from 'svelte-persisted-store';
import {get, type Writable} from 'svelte/store';
import {toggleDocumentAttribute} from "$lib/helpers/layout";

type LayoutType = {
    theme: 'light' | 'dark',
    topBarColor: 'light' | 'dark',
    leftSideBarColor: 'light' | 'dark',
    leftSideBarSize: 'sm-hover-active' | 'sm-hover' | 'hidden' | 'condensed' | 'default'
}

const defaultConfig: LayoutType = {
    theme: 'light',
    topBarColor: 'light',
    leftSideBarColor: 'light',
    leftSideBarSize: 'hidden'
}

export const layout: Writable<LayoutType> = persisted('reback_svelte_layout', defaultConfig);

let currentTheme: 'light' | 'dark';
let currentTopBarColor: 'light' | 'dark';
let currentLeftSideBarColor: 'light' | 'dark';
let currentLeftSideBarSize: 'sm-hover-active' | 'sm-hover' | 'hidden' | 'condensed' | 'default';

const applyLayoutToDocument = (current: LayoutType) => {
    if (typeof document === 'undefined') return;

    const root = document.documentElement;
    root.setAttribute('data-bs-theme', current.theme);
    root.setAttribute('data-topbar-color', current.topBarColor);
    root.setAttribute('data-menu-color', current.leftSideBarColor);
    root.setAttribute('data-menu-size', current.leftSideBarSize);
    root.style.colorScheme = current.theme;
};

layout.subscribe((current) => {
    currentTheme = current.theme;
    currentTopBarColor = current.topBarColor;
    currentLeftSideBarColor = current.leftSideBarColor;
    currentLeftSideBarSize = current.leftSideBarSize;
    applyLayoutToDocument(current);
});

const updateLayout = (key: keyof LayoutType, value: LayoutType[keyof LayoutType], attribute: string) => {
    layout.update(current => ({
        ...current,
        [key]: value
    }));
    toggleDocumentAttribute(attribute, value);
};

export const setTheme = (nTheme: LayoutType['theme']) => updateLayout('theme', nTheme, 'data-bs-theme');
export const setTopBarColor = (nColor: LayoutType['topBarColor']) => updateLayout('topBarColor', nColor, 'data-topbar-color');
export const setLeftSideBarColor = (nColor: LayoutType['leftSideBarColor']) => updateLayout('leftSideBarColor', nColor, 'data-menu-color');
export const setLeftSideBarSize = (nSize: LayoutType['leftSideBarSize']) => updateLayout('leftSideBarSize', nSize, 'data-menu-size');

export const toggleTheme = () => {
    const next = get(layout).theme === 'dark' ? 'light' : 'dark';
    setTheme(next);
    setTopBarColor(next);
    setLeftSideBarColor(next);
};

export const initLayout = () => {
    applyLayoutToDocument({
        theme: currentTheme,
        topBarColor: currentTopBarColor,
        leftSideBarColor: currentLeftSideBarColor,
        leftSideBarSize: currentLeftSideBarSize,
    });
};

export const resetLayout = () => {
    setTheme(defaultConfig.theme);
    setTopBarColor(defaultConfig.topBarColor);
    setLeftSideBarColor(defaultConfig.leftSideBarColor);
    setLeftSideBarSize(defaultConfig.leftSideBarSize);
};