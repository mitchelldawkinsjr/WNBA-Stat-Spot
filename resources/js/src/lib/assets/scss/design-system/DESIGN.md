---
name: ProCourts Analytics
colors:
  surface: '#0e141a'
  surface-dim: '#0e141a'
  surface-bright: '#343a40'
  surface-container-lowest: '#090f14'
  surface-container-low: '#161c22'
  surface-container: '#1a2026'
  surface-container-high: '#252b31'
  surface-container-highest: '#2f353c'
  on-surface: '#dde3eb'
  on-surface-variant: '#e1bfb4'
  inverse-surface: '#dde3eb'
  inverse-on-surface: '#2b3137'
  outline: '#a98a80'
  outline-variant: '#594139'
  surface-tint: '#ffb59b'
  primary: '#ffb59b'
  on-primary: '#5b1b00'
  primary-container: '#ff6c2f'
  on-primary-container: '#5d1c00'
  inverse-primary: '#a93800'
  secondary: '#a7c8ff'
  on-secondary: '#003060'
  secondary-container: '#3491fc'
  on-secondary-container: '#002a54'
  tertiary: '#bbc5ef'
  on-tertiary: '#252f51'
  tertiary-container: '#8e98c0'
  on-tertiary-container: '#263052'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#ffdbcf'
  primary-fixed-dim: '#ffb59b'
  on-primary-fixed: '#380d00'
  on-primary-fixed-variant: '#812900'
  secondary-fixed: '#d5e3ff'
  secondary-fixed-dim: '#a7c8ff'
  on-secondary-fixed: '#001c3b'
  on-secondary-fixed-variant: '#004787'
  tertiary-fixed: '#dce1ff'
  tertiary-fixed-dim: '#bbc5ef'
  on-tertiary-fixed: '#0f193b'
  on-tertiary-fixed-variant: '#3b4569'
  background: '#0e141a'
  on-background: '#dde3eb'
  surface-variant: '#2f353c'
  surface-dark: '#262d34'
  surface-dark-elevated: '#2c3238'
  success-over: '#22c55e'
  danger-under: '#ef5f5f'
  warning-amber: '#f9b931'
  info-cyan: '#4ecac2'
  border-subtle: '#2f3944'
  text-muted: '#aab8c5'
typography:
  display-lg:
    fontFamily: Hanken Grotesk
    fontSize: 36px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Hanken Grotesk
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Hanken Grotesk
    fontSize: 18px
    fontWeight: '600'
    lineHeight: 24px
  body-base:
    fontFamily: Hanken Grotesk
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 21px
  body-bold:
    fontFamily: Hanken Grotesk
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 21px
  label-caps:
    fontFamily: Hanken Grotesk
    fontSize: 12px
    fontWeight: '700'
    lineHeight: 14px
    letterSpacing: 0.06em
  stat-value:
    fontFamily: Hanken Grotesk
    fontSize: 16px
    fontWeight: '700'
    lineHeight: 16px
  display-lg-mobile:
    fontFamily: Hanken Grotesk
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 32px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 5px
  xs: 5px
  sm: 10px
  md: 20px
  lg: 30px
  xl: 60px
  gutter: 20px
  margin-desktop: 32px
  margin-mobile: 16px
---

## Brand & Style

This design system is built for high-performance sports analytics, blending the energy of the WNBA with the precision of a financial trading platform. The aesthetic is **Corporate / Modern** with a lean towards **Minimalism**, prioritizing data density and clarity over decorative flair. It targets serious fans and analysts who require a high-fidelity interface that remains legible during rapid data scanning.

The visual narrative is "Athletic Professionalism." It uses a structured grid, high-contrast typography, and a disciplined application of brand colors to create a hierarchy that highlights key insights without overwhelming the user. The UI feels authoritative, premium, and inherently data-centric, drawing inspiration from high-end editorial sports journalism.

## Colors

The color strategy uses a **dark-mode default** to mimic the immersive experience of a live sports broadcast. 

- **Primary (WNBA Fire Orange):** Reserved strictly for interactive elements, active states, and critical performance highlights.
- **Secondary (Electric Blue):** Used for non-critical interactive elements and data visualization trends.
- **Tertiary (Court Navy):** Acts as a structural anchor for headers or background layering to add depth.
- **Surface Roles:** The background uses a deep gunmetal (`#22282e`), while cards and containers use a slightly lighter slate (`#262d34`) to create distinct containment.
- **Semantic Colors:** Green and Red are used with high saturation to indicate "Over/Under" betting lines and positive/negative statistical trends.

## Typography

This design system uses **Hanken Grotesk** for its sharp, technical precision and athletic feel. It excels in tabular layouts where numeric clarity is paramount.

- **Headlines:** Use tighter letter spacing and heavier weights to create an "impact" editorial look.
- **Labels:** Small-caps are utilized for table headers and metadata to distinguish them from primary data points.
- **Numbers:** Statistical values should always use the `body-bold` or `stat-value` roles to ensure they are the first thing a user's eye gravitates toward.
- **Alignment:** Strictly left-align text labels; strictly right-align (tabular) numeric values in data tables to allow for easy vertical comparison.

## Layout & Spacing

The system follows a **12-column fluid grid** with a maximum container width of `1320px` to prevent data layouts from becoming overly stretched on ultrawide displays.

- **Grid:** A 20px gutter ensures clear "reading alleys" between dense stat cards.
- **Rhythm:** Spacing is strictly derivative of a 5px base unit. 
- **Density:** Dashboard layouts should utilize `spacing.sm` for internal component padding to maintain a high-information density, while using `spacing.lg` to separate major content sections.
- **Mobile Adaptivity:** On mobile, margins reduce to 16px, and 3-column desktop layouts reflow into a single vertical stack.

## Elevation & Depth

Visual hierarchy is achieved through **Tonal Layers** rather than heavy shadows, ensuring the UI remains clean and professional.

- **Surface Levels:** 
    - **Level 0 (Background):** `#22282e` — The primary canvas.
    - **Level 1 (Default Card):** `#262d34` — Used for the primary container surfaces.
    - **Level 2 (Hover/Active):** `#2c3238` — Used when a user interacts with a card or when an element is "raised" in the hierarchy.
- **Outlines:** Use "Low-contrast outlines" (`#2f3944`) to define component boundaries. This replaces the need for shadows in most instances, maintaining a flat, modern aesthetic.
- **Interaction:** A subtle `translateY(-2px)` animation on hover, paired with a very soft, low-opacity shadow, provides tactile feedback without cluttering the visual field.

## Shapes

The shape language is **Soft (0.25rem)** to maintain an athletic, precise feel. 

- **Standard Elements:** Buttons, input fields, and tags use the `0.25rem` radius.
- **Large Components:** Main content cards and feature banners use `0.5rem` (`rounded-lg`) to provide a slightly softer container for the dense data inside.
- **Speciality Shapes:** Statistical trend indicators and "Over/Under" badges use a full pill shape to differentiate them as status-driven elements.

## Components

- **Buttons:** 
    - **Primary:** Solid `#ff6c2f` with white text. High-energy, used for main actions.
    - **Secondary:** Outline style with `#1c84ee`. Used for secondary filtering or navigation.
- **Cards:** Must feature a `1px` solid border (`#2f3944`). Header sections of cards should have a subtle background tint or a 3px top-border accent color to categorize the data (e.g., Team colors).
- **Statistical Tables:** 
    - Row hover state: `#2c3238`. 
    - Use zebra-striping with `rgba(255, 255, 255, 0.02)` for long tables.
- **Chips & Badges:**
    - **Trend Chips:** Pill-shaped. Emerald green for "Hot/Up," Crimson red for "Cold/Down."
    - **Status Chips:** Use `label-caps` typography.
- **Input Fields:** Dark background (`#1a1f24`) with a subtle border. Focus state transitions the border to `#ff6c2f` without a glow effect.
- **Player Comparison:** Use side-by-side cards with mirrored layouts. The metric being compared should be highlighted with a vertical primary-colored bar when active.