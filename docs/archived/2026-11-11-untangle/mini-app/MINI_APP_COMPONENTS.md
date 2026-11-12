# Mini App UI Components Reference

**Last Updated:** 2026-03-03
**Status:** POC / Mockup Phase
**Location:** `mini-app/`

> **Context:** This document catalogs all UI elements in the Telegram Mini App mockup. The mini-app is an exploratory POC for handling core gameplay in a private overlay instead of flooding group chats.

> **Formatting Consistency:** When implementing data display in the mini-app, use the standardized formatting methods from `classes/FormatHelpers.php` to ensure consistency with the main bot UI. See `docs/VIEW_COMPONENTS.md` and `docs/MINI_APP_DATA_REFERENCE.md` for details.

---

## Table of Contents

1. [Design System](#design-system)
2. [Layout Components](#layout-components)
3. [Header Components](#header-components)
4. [Journey Components](#journey-components)
5. [Resource Components](#resource-components)
6. [Chad Components](#chad-components)
7. [Message Components](#message-components)
8. [Action Components](#action-components)
9. [Background Effects](#background-effects)
10. [Responsive Behavior](#responsive-behavior)
11. [Animation Reference](#animation-reference)

---

## Design System

### CSS Variables (`:root`)

#### Color Palette
```css
/* Deep Space Theme */
--color-space-black: #0a0e27
--color-space-dark: #0f1419
--color-space-darker: #070b1a

/* Accent Colors */
--color-cyan: #00d9ff
--color-cyan-dark: #00b8d4
--color-cyan-glow: rgba(0, 217, 255, 0.5)
--color-purple: #a855f7
--color-purple-dark: #7c3aed
--color-magenta: #d946ef

/* Status Colors */
--color-success: #22c55e      /* Green - good status */
--color-warning: #f59e0b      /* Orange - caution */
--color-danger: #ef4444       /* Red - critical */
--color-critical: #dc2626     /* Darker red - emergency */

/* Text Colors */
--color-text-primary: #e2e8f0    /* Main text */
--color-text-secondary: #94a3b8  /* Labels, metadata */
--color-text-muted: #64748b      /* Disabled, subtle */
--color-text-bright: #ffffff     /* Emphasis */

/* UI Elements */
--color-card-bg: rgba(15, 20, 25, 0.6)
--color-card-border: rgba(148, 163, 184, 0.1)
--color-glass-bg: rgba(30, 41, 59, 0.4)
```

#### Spacing Scale
```css
--space-xs: 0.25rem    /* 4px */
--space-sm: 0.5rem     /* 8px */
--space-md: 1rem       /* 16px */
--space-lg: 1.5rem     /* 24px */
--space-xl: 2rem       /* 32px */
```

#### Border Radius
```css
--radius-sm: 0.375rem  /* 6px */
--radius-md: 0.5rem    /* 8px */
--radius-lg: 0.75rem   /* 12px */
--radius-xl: 1rem      /* 16px */
```

#### Shadows & Effects
```css
--shadow-glow-cyan: 0 0 20px var(--color-cyan-glow)
--shadow-glow-purple: 0 0 20px rgba(168, 85, 247, 0.5)
--shadow-card: 0 4px 6px rgba(0, 0, 0, 0.3)
```

---

## Layout Components

### `.app-container`
**Purpose:** Main application wrapper
**Structure:**
```html
<div class="app-container">
  <header class="game-header">...</header>
  <main class="game-content">...</main>
  <div class="stars-bg"></div>
  <div class="nebula-bg"></div>
</div>
```

**Styling:**
- Max-width: 600px (centered)
- Padding: `var(--space-md)` (16px)
- Safe area inset support for iOS notch
- Positioned relative for absolute backgrounds
- Z-index: 1 (above background effects)

### `.game-content`
**Purpose:** Main content container
**Layout:** Flexbox column with gap spacing
**Children:** All game sections stack vertically

---

## Header Components

### `.game-header`
**Purpose:** Top-level mission info and day counter
**Layout:** Flex row with space-between
**Styling:**
- Glass morphism: backdrop-filter blur(10px)
- Border: 1px solid with low opacity
- Border-radius: `var(--radius-lg)` (12px)
- Box-shadow: card shadow
- Margin-bottom: `var(--space-lg)` (24px)

**Structure:**
```html
<header class="game-header">
  <div class="mission-title">
    <span class="mission-label">MISSION</span>
    <h1>OCCUPY URANUS</h1>
  </div>
  <div class="day-counter">
    <span class="day-label">DAY</span>
    <span class="day-number">42</span>
  </div>
</header>
```

### `.mission-title`
**Children:**
- `.mission-label` — Small cyan label ("MISSION")
  - Font-size: 0.625rem (10px)
  - Letter-spacing: 0.1em
  - Color: cyan
  - Uppercase

- `h1` — Main title
  - Font-family: 'Courier New', monospace
  - Font-size: 1.5rem (24px)
  - Gradient text: cyan → purple
  - Letter-spacing: 0.05em

### `.day-counter`
**Children:**
- `.day-label` — Small secondary label ("DAY")
  - Font-size: 0.625rem (10px)
  - Uppercase

- `.day-number` — Large numeric display
  - Font-family: 'Courier New', monospace
  - Font-size: 2rem (32px)
  - Color: cyan with glow effect
  - Text-shadow: cyan glow

---

## Journey Components

### `.journey-section`
**Purpose:** Progress tracking and waypoint visualization
**Styling:** Glass card with border and shadow

**Structure:**
```html
<section class="journey-section">
  <div class="distance-info">...</div>
  <div class="progress-bar-container">
    <div class="progress-bar">...</div>
    <div class="waypoint-markers">...</div>
  </div>
  <div class="location-label">...</div>
</section>
```

### `.distance-info`
**Purpose:** Current and remaining distance display
**Layout:** Flex row, centered, with gaps
**Children:**
- `.current-distance` — Cyan, bold (e.g., "1,240,000,000 km")
- `.separator` — Muted bullet point ("•")
- `.remaining-distance` — Secondary color (e.g., "1,560,000,000 km to go")

### `.progress-bar-container`
**Purpose:** Wrapper for progress bar and waypoints
**Spacing:** Margin-bottom for location label

### `.progress-bar`
**Purpose:** Visual progress indicator
**Dimensions:** Height 24px, full width
**Styling:**
- Background: space-dark
- Border-radius: `var(--radius-xl)` (16px)
- Border: 1px solid low opacity
- Overflow: visible (for ship icon)
- Position: relative

**Children:**
- `.progress-fill` — Animated gradient fill
  - Background: Linear gradient purple-dark → cyan
  - Width: Dynamic (e.g., 44% for current progress)
  - Transition: width 1s ease
  - Contains `.progress-glow` (shimmer effect)

- `.ship-icon` (🚀)
  - Position: Absolute at progress percentage
  - Font-size: 1.5rem (24px)
  - Filter: Cyan drop-shadow
  - Animation: Vertical hover (2s infinite)

### `.progress-glow`
**Purpose:** Animated shimmer effect on progress fill
**Animation:** Horizontal translate -100% → 100% (2s infinite)
**Styling:** White gradient overlay with opacity

### `.waypoint-markers`
**Purpose:** Planetary waypoint indicators
**Layout:** Flex row, space-between
**Children:** `.waypoint` elements (emoji planets/stations)

**Waypoint States:**
```html
<div class="waypoint passed">🌍</div>    <!-- Earth - completed -->
<div class="waypoint passed">🔴</div>    <!-- Mars - completed -->
<div class="waypoint passed">🪐</div>    <!-- Jupiter Station - completed -->
<div class="waypoint current">♃</div>   <!-- Jupiter - current -->
<div class="waypoint future">🪐</div>    <!-- Saturn - upcoming -->
<div class="waypoint future">♆</div>    <!-- Neptune - upcoming -->
<div class="waypoint future">⛢</div>    <!-- Uranus - destination -->
```

**Waypoint Styling:**
- `.waypoint` — Base: 1.5rem font, 0.3 opacity
- `.waypoint.passed` — 0.5 opacity (dim)
- `.waypoint.current` — Full opacity, cyan glow, pulsing scale animation
- `.waypoint.future` — 0.3 opacity (very dim)

### `.location-label`
**Purpose:** Text description of current location
**Styling:**
- Text-align: center
- Font-size: 0.875rem (14px)
- Color: secondary
- Font-style: italic
- Example: "En Route to Saturn Station"

---

## Resource Components

### `.resources-section`
**Purpose:** Grid of resource status cards
**Layout:** CSS Grid, 2 columns on mobile, 3 on tablet+
**Gap:** `var(--space-md)` (16px)

**Structure:**
```html
<section class="resources-section">
  <div class="resource-card fuel">...</div>
  <div class="resource-card food">...</div>
  <div class="resource-card hull">...</div>
  <div class="resource-card morale">...</div>
  <div class="resource-card credits">...</div>
</section>
```

### `.resource-card`
**Purpose:** Individual resource display
**Styling:**
- Glass morphism background
- Border with hover effect
- Padding: `var(--space-md)` (16px)
- Border-radius: `var(--radius-md)` (8px)
- Transition: all 0.3s ease

**Hover State:**
- Border-color: cyan
- Box-shadow: cyan glow
- Transform: translateY(-2px)

**Variants:**
- `.resource-card.fuel` — Orange fill color
- `.resource-card.food` — Green fill color
- `.resource-card.hull` — Cyan fill color
- `.resource-card.morale` — Purple fill color
- `.resource-card.credits` — Special layout (no bar, large value)

### Resource Card Structure
```html
<div class="resource-card fuel">
  <div class="resource-header">
    <span class="resource-icon">⛽</span>
    <span class="resource-name">FUEL</span>
  </div>
  <div class="resource-bar">
    <div class="resource-fill" style="width: 68%"></div>
  </div>
  <div class="resource-value">815 / 1200</div>
</div>
```

### `.resource-header`
**Layout:** Flex row with gap
**Children:**
- `.resource-icon` — Emoji (1.25rem)
- `.resource-name` — Uppercase label (0.75rem, secondary color)

### `.resource-bar`
**Purpose:** Progress bar for resource level
**Dimensions:** Height 6px
**Styling:**
- Background: space-dark
- Border-radius: pill shape
- Overflow: hidden
- Margin-bottom: `var(--space-sm)` (8px)

### `.resource-fill`
**Purpose:** Colored fill indicating current level
**Styling:**
- Width: Dynamic percentage (inline style)
- Border-radius: pill shape
- Transition: width 0.5s ease
- Box-shadow: Glow matching fill color
- Color varies by resource type (warning/success/cyan/purple)

### `.resource-value`
**Purpose:** Numeric display of resource amount
**Variants:**
- Standard: 0.875rem, "815 / 1200" format
- `.large` (for credits): 1.5rem, monospace, cyan with glow

### `.credits` Card (Special)
**Layout:** Grid-column: span 2 (full width on 2-col grid)
**Styling:** Text-align center
**Structure:**
```html
<div class="resource-card credits">
  <div class="resource-header">
    <span class="resource-icon">💎</span>
    <span class="resource-name">ETH</span>
  </div>
  <div class="resource-value large">450.5</div>
</div>
```

---

## Chad Components

### `.chad-section`
**Purpose:** Display all chad status
**Styling:** Glass card wrapper with padding

**Structure:**
```html
<section class="chad-section">
  <h2 class="section-title">CREW STATUS</h2>
  <div class="chad-grid">...</div>
</section>
```

### `.section-title`
**Purpose:** Section header (reusable pattern)
**Styling:**
- Font-size: 0.75rem (12px)
- Letter-spacing: 0.15em
- Color: secondary
- Uppercase
- Margin-bottom: `var(--space-md)` (16px)

### `.chad-grid`
**Purpose:** Grid layout for chad cards
**Layout:** CSS Grid, 2 columns
**Gap:** `var(--space-md)` (16px)

### `.chad-card`
**Purpose:** Individual chad display
**Layout:** Flex row with gap
**Styling:**
- Background: card-bg (darker than main cards)
- Border: 1px solid low opacity
- Border-radius: `var(--radius-md)` (8px)
- Padding: `var(--space-md)` (16px)
- Transition: all 0.3s ease

**States:**
- `.chad-card.alive` — Default styling
  - Hover: Green border + green glow + translateY(-2px)
  - Status icon: ✓ (green)

- `.chad-card.deceased` — Visual distinction
  - Opacity: 0.5
  - Filter: grayscale(1)
  - Avatar: Brightness reduced
  - Status icon: † (muted)
  - No hover effect

**Structure:**
```html
<div class="chad-card alive">
  <div class="chad-avatar">👨‍🚀</div>
  <div class="chad-info">
    <div class="chad-name">Alex Chen</div>
    <div class="chad-role">Captain</div>
  </div>
  <div class="chad-status">✓</div>
</div>
```

### `.chad-avatar`
**Purpose:** Emoji character representation
**Styling:** Font-size: 2rem (32px)

### `.chad-info`
**Purpose:** Name and role container
**Flex:** 1 (takes remaining space)

### `.chad-name`
**Purpose:** Character name display
**Styling:**
- Font-size: 0.875rem (14px)
- Font-weight: 600
- Color: primary

### `.chad-role`
**Purpose:** Character role/job display
**Styling:**
- Font-size: 0.75rem (12px)
- Color: secondary

### `.chad-status`
**Purpose:** Status icon (alive/deceased)
**Styling:** Font-size: 1.25rem (20px)

---

## Message Components

### `.flash-message`
**Purpose:** Temporary notification/event message
**Layout:** Flex row with gap
**Styling:**
- Background: Gradient (purple-cyan overlay)
- Border: 1px solid cyan
- Border-radius: `var(--radius-md)` (8px)
- Padding: `var(--space-md)` (16px)
- Box-shadow: Cyan glow
- Animation: Fade-in from top (0.5s)

**Structure:**
```html
<div class="flash-message">
  <span class="flash-icon">📡</span>
  <span class="flash-text">You found 50 units of food while hunting! The chad is pleased.</span>
</div>
```

**Animation (`flash-fade-in`):**
- From: opacity 0, translateY(-10px)
- To: opacity 1, translateY(0)
- Duration: 0.5s

### `.flash-icon`
**Purpose:** Icon for message type
**Styling:** Font-size: 1.5rem (24px)

### `.flash-text`
**Purpose:** Message content
**Styling:**
- Flex: 1 (takes remaining space)
- Font-size: 0.875rem (14px)
- Color: primary

---

## Action Components

### `.actions-section`
**Purpose:** Button container for player actions
**Layout:** Flex column with gap
**Spacing:** Margin-bottom: `var(--space-xl)` (32px)

**Structure:**
```html
<section class="actions-section">
  <button class="action-btn primary pulse">
    <span class="btn-icon">🚀</span>
    <span class="btn-label">CONTINUE</span>
  </button>
  <div class="action-grid">
    <button class="action-btn secondary">...</button>
    <!-- More buttons -->
  </div>
</section>
```

### `.action-btn`
**Purpose:** Interactive button component
**Layout:** Flex row, centered content
**Base Styling:**
- Background: Glass morphism
- Border: 2px solid low opacity
- Border-radius: `var(--radius-lg)` (12px)
- Padding: `var(--space-md) var(--space-lg)` (16px 24px)
- Color: primary text
- Font-size: 1rem
- Font-weight: 600
- Letter-spacing: 0.05em
- Cursor: pointer
- Transition: all 0.3s ease
- Box-shadow: card shadow

**Active State:**
- Transform: scale(0.98)

### `.btn-icon`
**Purpose:** Emoji icon prefix
**Styling:** Font-size: 1.25rem (20px)

### `.btn-label`
**Purpose:** Button text label
**Styling:**
- Font-family: 'Courier New', monospace
- Text-transform: uppercase

### `.action-btn.primary`
**Purpose:** Main action button (CONTINUE)
**Enhanced Styling:**
- Background: Linear gradient (purple-dark → cyan-dark)
- Border-color: cyan
- Font-size: 1.125rem (18px)
- Padding: `var(--space-lg) var(--space-xl)` (24px 32px)
- Box-shadow: Cyan glow

**Hover:**
- Box-shadow: Enhanced cyan glow (30px spread)
- Transform: translateY(-2px)

**With `.pulse` modifier:**
- Animation: Pulsing glow (2s infinite)
- Shadow alternates between 20px and 40px spread with purple accent

### `.action-grid`
**Purpose:** Grid layout for secondary actions
**Layout:** CSS Grid, 2 columns (4 on desktop)
**Gap:** `var(--space-md)` (16px)

### `.action-btn.secondary`
**Purpose:** Secondary action buttons
**Modified Styling:**
- Font-size: 0.875rem (14px)
- Padding: `var(--space-md)` (16px)

**Hover:**
- Border-color: purple
- Box-shadow: Purple glow
- Transform: translateY(-2px)

---

## Background Effects

### `.stars-bg`
**Purpose:** Animated starfield effect
**Position:** Fixed, full viewport
**Z-index:** 0 (behind content)
**Styling:**
- Multiple radial-gradient layers (varying sizes)
- Background-size: 200% 200%
- Animation: `stars-float` (60s infinite)
- Opacity: 0.5

**Animation (`stars-float`):**
- 0%/100%: background-position 0% 0%
- 50%: background-position 100% 100%
- Creates slow parallax drift

### `.nebula-bg`
**Purpose:** Colored nebula effect
**Position:** Fixed, full viewport
**Z-index:** 0 (behind content)
**Styling:**
- Three radial-gradient ellipses (purple, cyan, magenta)
- Positioned at different viewport coordinates
- Transparent falloff
- Animation: `nebula-pulse` (20s infinite)

**Animation (`nebula-pulse`):**
- 0%/100%: opacity 0.3
- 50%: opacity 0.6
- Creates breathing effect

---

## Responsive Behavior

### Mobile First (< 480px)
**Default layout:**
- Resources: 2-column grid
- Chad: 2-column grid
- Action grid: 2 columns
- Credits card: Spans 2 columns

### Tablet (≥ 480px)
**Changes:**
- Resources: 3-column grid
- Credits card: Spans 3 columns
- Chad: Stays 2-column

### Desktop (≥ 600px)
**Changes:**
- App container: Padding increases to `var(--space-xl)` (32px)
- Action grid: 4 columns (all secondary buttons in one row)

---

## Animation Reference

### Continuous Animations

| Animation Name | Duration | Element | Effect |
|---------------|----------|---------|--------|
| `stars-float` | 60s | `.stars-bg` | Slow parallax drift |
| `nebula-pulse` | 20s | `.nebula-bg` | Breathing opacity |
| `progress-shimmer` | 2s | `.progress-glow` | Horizontal sweep |
| `ship-hover` | 2s | `.ship-icon` | Vertical float |
| `waypoint-pulse` | 2s | `.waypoint.current` | Scale pulse |
| `button-pulse` | 2s | `.action-btn.primary.pulse` | Glow pulse |

### Transition Animations

| Element | Transition | Duration |
|---------|-----------|----------|
| `.progress-fill` | Width change | 1s ease |
| `.resource-fill` | Width change | 0.5s ease |
| `.resource-card` | Hover effects | 0.3s ease |
| `.chad-card` | Hover effects | 0.3s ease |
| `.action-btn` | Hover/active | 0.3s ease |
| `.waypoint` | State change | 0.3s ease |

### One-Time Animations

| Animation Name | Duration | Element | Trigger |
|---------------|----------|---------|---------|
| `flash-fade-in` | 0.5s | `.flash-message` | On appear |

---

## Component Hierarchy Tree

```
.app-container
├── .game-header
│   ├── .mission-title
│   │   ├── .mission-label
│   │   └── h1
│   └── .day-counter
│       ├── .day-label
│       └── .day-number
│
├── .game-content
│   ├── .journey-section
│   │   ├── .distance-info
│   │   │   ├── .current-distance
│   │   │   ├── .separator
│   │   │   └── .remaining-distance
│   │   ├── .progress-bar-container
│   │   │   ├── .progress-bar
│   │   │   │   ├── .progress-fill
│   │   │   │   │   └── .progress-glow
│   │   │   │   └── .ship-icon
│   │   │   └── .waypoint-markers
│   │   │       └── .waypoint [.passed|.current|.future]
│   │   └── .location-label
│   │
│   ├── .resources-section
│   │   └── .resource-card [.fuel|.food|.hull|.morale|.credits]
│   │       ├── .resource-header
│   │       │   ├── .resource-icon
│   │       │   └── .resource-name
│   │       ├── .resource-bar
│   │       │   └── .resource-fill
│   │       └── .resource-value [.large]
│   │
│   ├── .chad-section
│   │   ├── .section-title
│   │   └── .chad-grid
│   │       └── .chad-card [.alive|.deceased]
│   │           ├── .chad-avatar
│   │           ├── .chad-info
│   │           │   ├── .chad-name
│   │           │   └── .chad-role
│   │           └── .chad-status
│   │
│   ├── .flash-message
│   │   ├── .flash-icon
│   │   └── .flash-text
│   │
│   └── .actions-section
│       ├── .action-btn.primary [.pulse]
│       │   ├── .btn-icon
│       │   └── .btn-label
│       └── .action-grid
│           └── .action-btn.secondary
│               ├── .btn-icon
│               └── .btn-label
│
├── .stars-bg
└── .nebula-bg
```

---

## Usage Notes

### Dynamic Data (Will Need JS)

**Values that should update from game state:**
- `.day-number` — Current day count
- `.current-distance` — Distance traveled (km)
- `.remaining-distance` — Distance remaining (km)
- `.progress-fill` width — Progress percentage
- `.ship-icon` left position — Matches progress percentage
- `.waypoint` states — Update passed/current/future as player progresses
- `.location-label` — Current/next waypoint text
- `.resource-fill` width — Resource percentages
- `.resource-value` — Current/max values
- `.resource-value.large` — Credit amount
- `.chad-card` states — alive/deceased
- All chad data (avatar, name, role, status)
- `.flash-message` — Show/hide with dynamic content

### Interactive Elements (Need Event Handlers)

**Buttons requiring click handlers:**
- `.action-btn.primary` — Continue/advance turn
- Hunt button — Open hunting mini-game
- Status button — Toggle detailed status view
- Map button — Show journey map/waypoints
- Aesthetic button — Change visual theme/aesthetic

**Potential interactions:**
- `.resource-card` hover/click — Show detailed resource info
- `.chad-card` click — Show chad details/history
- `.waypoint` click — Show waypoint information
- `.flash-message` — Auto-dismiss after timeout

### Telegram Integration Points

**SDK Functions to Implement:**
```javascript
// Initialize app
Telegram.WebApp.ready()

// Get user authentication
const initData = Telegram.WebApp.initData

// Control main button (alternative to in-app CONTINUE button)
Telegram.WebApp.MainButton.setText("CONTINUE")
Telegram.WebApp.MainButton.show()
Telegram.WebApp.MainButton.onClick(handler)

// Haptic feedback on interactions
Telegram.WebApp.HapticFeedback.impactOccurred('medium')

// Close mini app
Telegram.WebApp.close()
```

---

## File References

**HTML Structure:** `mini-app/index.html`
**Styling:** `mini-app/style.css`
**JavaScript:** `mini-app/app.js` (not yet created)
**Documentation:** This file

**Related Documentation:**
- Bot views: `docs/GAME_VIEWS.md`
- Session data: `docs/SESSION_STATE.md`
- Architecture: `docs/ARCHITECTURE.md`
