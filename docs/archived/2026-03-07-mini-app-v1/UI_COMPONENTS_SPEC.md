# Mini-App Missing UI Components Specification

**Last Updated:** 2026-03-07
**Status:** Design Specification
**Purpose:** Complete specification for all missing UI components

---

## Design System Reference

### Existing Visual Language

**Theme:** Cockpit/Dashboard with 3D depth effects
**Colors:**
- `--space-deep: #030810` - Background
- `--glass-bg: rgba(10, 16, 34, 0.55)` - Panel backgrounds
- `--glass-border: rgba(120, 150, 210, 0.22)` - Panel borders
- `--amber: #ffb833` - Primary accent (warnings, ETH)
- `--teal: #44ddcc` - Secondary accent
- `--green: #55ee77` - Success states
- `--red: #ff5566` - Danger/critical
- `--orange: #ff9944` - Warnings

**Typography:**
- Headers: `Orbitron` (tech/futuristic)
- Body: `Nunito` (readable)
- Monospace: `Azeret Mono` (data/stats)

**3D Effects:**
- All panels use `transform-style: preserve-3d`
- `.panel-face` - Front surface (glass morphism)
- `.panel-depth` - Back surface at `translateZ(-8px)`
- `.panel-glass` - Top shine gradient
- `.stud-3d` - Corner rivets

**Button Pattern:**
- `.btn-3d-main` - Primary action (green, large)
- `.btn-3d` - Secondary actions (glass, grid layout)
- Both have depth layers and hover transform

---

## Component 1: Event Modal

### Purpose
Display random events with choices when `outcome: 'EVENT_TRIGGERED'`

### Data Structure
```json
{
  "id": "distress_signal",
  "title": "🚨 DISTRESS SIGNAL 🚨",
  "description": "Your comms crackle with a weak distress signal...",
  "type": "discovery",
  "choices": [
    {
      "id": "investigate",
      "text": "Investigate and attempt rescue",
      "outcomes": [...]
    },
    {
      "id": "ignore",
      "text": "Ignore and continue",
      "outcomes": [...]
    }
  ]
}
```

### HTML Structure
```html
<!-- Event Modal Overlay -->
<div id="eventModal" class="modal-overlay" style="display: none;">
    <div class="modal-backdrop"></div>
    <div class="cockpit-viewport">
        <div class="cockpit-scene">
            <div class="dashboard-scroll">
                <div class="dashboard" style="padding-top: 60px;">

                    <!-- Event Panel -->
                    <div class="panel-3d event-panel">
                        <div class="panel-depth"></div>
                        <div class="panel-face">
                            <div class="panel-glass"></div>
                            <div class="stud-3d" style="top:12px;left:16px"></div>
                            <div class="stud-3d" style="top:12px;right:16px"></div>
                            <div class="stud-3d" style="bottom:12px;left:16px"></div>
                            <div class="stud-3d" style="bottom:12px;right:16px"></div>

                            <div class="event-content">
                                <!-- Header -->
                                <div class="event-header">
                                    <div class="event-badge">
                                        <span class="badge-icon">📡</span>
                                        <span class="badge-text">Random Event</span>
                                    </div>
                                </div>

                                <!-- Title -->
                                <h2 class="event-title" id="eventTitle"></h2>

                                <!-- Description -->
                                <div class="event-description" id="eventDescription"></div>

                                <!-- Choices -->
                                <div class="event-choices" id="eventChoices">
                                    <!-- Dynamically populated -->
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
```

### CSS Styles
```css
/* Modal Overlay */
.modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: modal-fade-in 0.3s ease;
}

@keyframes modal-fade-in {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

/* Event Panel */
.event-panel {
    transform: scale(0.95) translateZ(20px);
    animation: event-panel-enter 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

@keyframes event-panel-enter {
    from {
        transform: scale(0.85) translateZ(0px);
        opacity: 0;
    }
    to {
        transform: scale(1) translateZ(20px);
        opacity: 1;
    }
}

.event-content {
    padding: 24px 20px;
    transform-style: preserve-3d;
}

/* Event Header */
.event-header {
    display: flex;
    justify-content: center;
    margin-bottom: 20px;
    transform: translateZ(6px);
}

.event-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: rgba(68, 221, 204, 0.1);
    border: 1px solid rgba(68, 221, 204, 0.3);
    border-radius: 20px;
    box-shadow: 0 0 20px rgba(68, 221, 204, 0.2);
}

.badge-icon {
    font-size: 18px;
    line-height: 1;
}

.badge-text {
    font-family: 'Azeret Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--teal);
}

/* Event Title */
.event-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: var(--text-bright);
    text-align: center;
    margin-bottom: 16px;
    transform: translateZ(4px);
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
}

/* Event Description */
.event-description {
    font-family: 'Nunito', sans-serif;
    font-size: 13px;
    line-height: 1.6;
    color: var(--text-soft);
    text-align: center;
    margin-bottom: 24px;
    padding: 0 12px;
    transform: translateZ(2px);
}

/* Event Choices */
.event-choices {
    display: flex;
    flex-direction: column;
    gap: 12px;
    transform: translateZ(6px);
}

.event-choice-btn {
    display: block;
    width: 100%;
    transform-style: preserve-3d;
    position: relative;
    cursor: pointer;
    border: none;
    background: none;
    padding: 0;
    transform: translateZ(4px);
    transition: transform 0.15s ease;
}

.event-choice-btn:hover {
    transform: translateZ(10px);
}

.event-choice-btn:active {
    transform: translateZ(0px);
}

.event-choice-face {
    padding: 16px 20px;
    background: radial-gradient(ellipse 80% 100% at 50% 120%, rgba(255,184,51,0.06) 0%, transparent 60%), rgba(14,20,36,0.7);
    border: 1.5px solid rgba(255,184,51,0.25);
    border-radius: 16px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.45);
    position: relative;
    overflow: hidden;
    transform: translateZ(4px);
    text-align: left;
}

.event-choice-face::before {
    content: '';
    position: absolute;
    top: 0;
    left: 10%;
    right: 10%;
    height: 45%;
    border-radius: 16px 16px 50% 50%;
    background: linear-gradient(180deg, rgba(255,255,255,0.08) 0%, transparent 100%);
    pointer-events: none;
}

.event-choice-depth {
    position: absolute;
    inset: 0;
    border-radius: 16px;
    background: linear-gradient(180deg, rgba(20, 30, 40, 0.8), rgba(10, 15, 20, 0.9));
    border: 1px solid rgba(255,184,51,0.1);
    transform: translateZ(-2px);
}

.event-choice-text {
    font-family: 'Nunito', sans-serif;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-bright);
    margin-bottom: 6px;
}

.event-choice-meta {
    display: flex;
    gap: 12px;
    font-family: 'Azeret Mono', monospace;
    font-size: 9px;
    color: var(--text-label);
    letter-spacing: 0.05em;
}

.event-choice-btn:hover .event-choice-face {
    border-color: rgba(255,184,51,0.4);
    box-shadow: 0 8px 24px rgba(0,0,0,0.5), 0 0 20px rgba(255,184,51,0.15);
}
```

### JavaScript Handler
```javascript
showEventModal(event) {
    const modal = document.getElementById('eventModal');
    const title = document.getElementById('eventTitle');
    const description = document.getElementById('eventDescription');
    const choices = document.getElementById('eventChoices');

    // Set content
    title.textContent = event.title;
    description.textContent = event.description;

    // Build choices
    choices.innerHTML = '';
    event.choices.forEach(choice => {
        const btn = document.createElement('button');
        btn.className = 'event-choice-btn';
        btn.innerHTML = `
            <div class="event-choice-depth"></div>
            <div class="event-choice-face">
                <div class="event-choice-text">${choice.text}</div>
                <div class="event-choice-meta">
                    ${choice.cost ? `<span>💰 ${choice.cost}</span>` : ''}
                    ${choice.risk ? `<span>⚠️ ${choice.risk}</span>` : ''}
                </div>
            </div>
        `;
        btn.onclick = () => this.handleEventChoice(event.id, choice.id);
        choices.appendChild(btn);
    });

    // Show modal
    modal.style.display = 'flex';
    this.uiState = 'event';
}

async handleEventChoice(eventId, choiceId) {
    document.getElementById('eventModal').style.display = 'none';
    this.uiState = 'journey';

    await this.executeAction('process_event', {
        event_id: eventId,
        choice_id: choiceId
    });
}
```

---

## Component 2: Game Over Screen

### Purpose
Full-screen overlay when `outcome: 'GAME_OVER'`

### Data Structure
```json
{
  "outcome": "GAME_OVER",
  "context": {
    "reason": "Out of fuel"
  },
  "session": {
    "progress": { "day": 42, "distance": 1240000000 },
    "stats": { "chad_lost": 2, "fuel_consumed": 850 }
  }
}
```

### HTML Structure
```html
<div id="gameOverScreen" class="full-screen-overlay" style="display: none;">
    <div class="full-screen-backdrop"></div>
    <div class="full-screen-content">

        <!-- Skull Icon -->
        <div class="game-over-icon">💀</div>

        <!-- Title -->
        <h1 class="game-over-title">MISSION FAILED</h1>

        <!-- Reason -->
        <div class="game-over-reason" id="gameOverReason"></div>

        <!-- Stats Panel -->
        <div class="game-over-stats panel-3d">
            <div class="panel-depth"></div>
            <div class="panel-face">
                <div class="panel-glass"></div>
                <div class="stats-grid" id="gameOverStats">
                    <!-- Populated dynamically -->
                </div>
            </div>
        </div>

        <!-- New Game Button -->
        <button class="btn-3d-main" onclick="location.reload()">
            <div class="btn-main-depth"></div>
            <div class="btn-main-face">
                <span class="btn-icon">🔄</span>
                <span class="btn-text">New Game</span>
            </div>
        </button>

    </div>
</div>
```

### CSS Styles
```css
.full-screen-overlay {
    position: fixed;
    inset: 0;
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: screen-fade-in 0.6s ease;
}

.full-screen-backdrop {
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at center, rgba(255, 85, 102, 0.15), rgba(3, 8, 16, 0.95));
    backdrop-filter: blur(12px);
}

.full-screen-content {
    position: relative;
    max-width: 400px;
    padding: 40px 24px;
    text-align: center;
    transform-style: preserve-3d;
    animation: content-float-in 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes content-float-in {
    from {
        transform: translateY(40px) scale(0.9);
        opacity: 0;
    }
    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}

.game-over-icon {
    font-size: 80px;
    line-height: 1;
    margin-bottom: 20px;
    filter: drop-shadow(0 0 20px rgba(255, 85, 102, 0.5));
    animation: skull-pulse 2s ease-in-out infinite;
}

@keyframes skull-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.game-over-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 28px;
    font-weight: 900;
    letter-spacing: 0.1em;
    color: var(--red);
    text-shadow: 0 0 30px var(--red-glow), 0 4px 12px rgba(0,0,0,0.8);
    margin-bottom: 16px;
}

.game-over-reason {
    font-family: 'Nunito', sans-serif;
    font-size: 15px;
    color: var(--text-soft);
    margin-bottom: 32px;
    padding: 0 20px;
}

.game-over-stats {
    margin-bottom: 32px;
    transform: translateZ(10px);
}

.game-over-stats .panel-face {
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.stat-item {
    text-align: center;
}

.stat-value {
    font-family: 'Orbitron', sans-serif;
    font-size: 24px;
    font-weight: 700;
    color: var(--amber);
    text-shadow: 0 0 12px var(--amber-glow);
    margin-bottom: 4px;
}

.stat-label {
    font-family: 'Azeret Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-label);
}
```

### JavaScript Handler
```javascript
showGameOverScreen(reason) {
    const screen = document.getElementById('gameOverScreen');
    const reasonEl = document.getElementById('gameOverReason');
    const statsEl = document.getElementById('gameOverStats');

    // Set reason
    reasonEl.textContent = reason;

    // Build stats
    const stats = this.session.stats;
    const progress = this.session.progress;

    statsEl.innerHTML = `
        <div class="stat-item">
            <div class="stat-value">${progress.day}</div>
            <div class="stat-label">Days Survived</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">${this.formatDistance(progress.distance)}</div>
            <div class="stat-label">Distance Traveled</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">${stats.chad_lost || 0}</div>
            <div class="stat-label">Chad Lost</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">${Math.round((progress.distance / 2800000000) * 100)}%</div>
            <div class="stat-label">Progress</div>
        </div>
    `;

    // Show screen
    screen.style.display = 'flex';
    this.uiState = 'game_over';

    // Stop polling
    this.stopPolling();
}
```

---

## Component 3: Victory Screen

### Purpose
Celebration screen when `outcome: 'VICTORY'`

### HTML Structure
```html
<div id="victoryScreen" class="full-screen-overlay" style="display: none;">
    <div class="full-screen-backdrop victory-backdrop"></div>

    <!-- Confetti Canvas -->
    <canvas id="confettiCanvas" class="confetti-layer"></canvas>

    <div class="full-screen-content">

        <!-- Trophy Icon -->
        <div class="victory-icon">🏆</div>

        <!-- Title -->
        <h1 class="victory-title">URANUS OCCUPIED!</h1>

        <!-- Subtitle -->
        <div class="victory-subtitle">Mission Accomplished</div>

        <!-- Stats Panel -->
        <div class="victory-stats panel-3d">
            <div class="panel-depth"></div>
            <div class="panel-face">
                <div class="panel-glass"></div>
                <div class="stats-grid" id="victoryStats">
                    <!-- Populated dynamically -->
                </div>
            </div>
        </div>

        <!-- New Game Button -->
        <button class="btn-3d-main victory-btn" onclick="location.reload()">
            <div class="btn-main-depth"></div>
            <div class="btn-main-face">
                <span class="btn-icon">🔄</span>
                <span class="btn-text">New Game</span>
            </div>
        </button>

    </div>
</div>
```

### CSS Styles
```css
.victory-backdrop {
    background: radial-gradient(ellipse at center, rgba(85, 238, 119, 0.15), rgba(3, 8, 16, 0.95));
}

.confetti-layer {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.victory-icon {
    font-size: 100px;
    line-height: 1;
    margin-bottom: 20px;
    filter: drop-shadow(0 0 30px rgba(255, 184, 51, 0.8));
    animation: trophy-spin 3s cubic-bezier(0.34, 1.56, 0.64, 1) infinite;
}

@keyframes trophy-spin {
    0%, 100% { transform: rotate(0deg) scale(1); }
    25% { transform: rotate(-10deg) scale(1.1); }
    50% { transform: rotate(10deg) scale(1); }
    75% { transform: rotate(-5deg) scale(1.05); }
}

.victory-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 32px;
    font-weight: 900;
    letter-spacing: 0.08em;
    background: linear-gradient(135deg, var(--amber), var(--green));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 4px 20px rgba(85, 238, 119, 0.5);
    margin-bottom: 8px;
}

.victory-subtitle {
    font-family: 'Azeret Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--teal);
    margin-bottom: 32px;
}

.victory-stats {
    margin-bottom: 32px;
}

.victory-btn .btn-main-face {
    background: linear-gradient(180deg, rgba(85,238,119,0.2) 0%, rgba(40,100,60,0.5) 50%, rgba(20,60,30,0.65) 100%);
    border-color: rgba(85,238,119,0.4);
}
```

### JavaScript Handler (includes confetti)
```javascript
showVictoryScreen() {
    const screen = document.getElementById('victoryScreen');
    const statsEl = document.getElementById('victoryStats');

    // Build stats
    const stats = this.session.stats;
    const progress = this.session.progress;

    statsEl.innerHTML = `
        <div class="stat-item">
            <div class="stat-value">${progress.day}</div>
            <div class="stat-label">Total Days</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">${this.formatDistance(stats.total_distance_traveled)}</div>
            <div class="stat-label">Distance</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">${5 - (stats.chad_lost || 0)}</div>
            <div class="stat-label">Chad Survived</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">${stats.events_encountered || 0}</div>
            <div class="stat-label">Events</div>
        </div>
    `;

    // Show screen
    screen.style.display = 'flex';
    this.uiState = 'victory';

    // Start confetti
    this.startConfetti();

    // Stop polling
    this.stopPolling();
}

startConfetti() {
    const canvas = document.getElementById('confettiCanvas');
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    const particles = [];
    const colors = ['#ffb833', '#55ee77', '#44ddcc', '#ff9944'];

    for (let i = 0; i < 100; i++) {
        particles.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height - canvas.height,
            vx: (Math.random() - 0.5) * 2,
            vy: Math.random() * 3 + 2,
            rotation: Math.random() * 360,
            vr: (Math.random() - 0.5) * 10,
            size: Math.random() * 8 + 4,
            color: colors[Math.floor(Math.random() * colors.length)]
        });
    }

    function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        particles.forEach(p => {
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rotation * Math.PI / 180);
            ctx.fillStyle = p.color;
            ctx.fillRect(-p.size/2, -p.size/2, p.size, p.size);
            ctx.restore();

            p.x += p.vx;
            p.y += p.vy;
            p.rotation += p.vr;
            p.vy += 0.1; // gravity

            if (p.y > canvas.height) {
                p.y = -10;
                p.x = Math.random() * canvas.width;
            }
        });

        requestAnimationFrame(animate);
    }
    animate();
}
```

---

## Component 4: Waypoint Decision Modal

### Purpose
Modal when arriving at station, `outcome: 'WAYPOINT_REACHED'`

### Data Structure
```json
{
  "outcome": "WAYPOINT_REACHED",
  "context": {
    "waypoint": {
      "id": "jupiter",
      "name": "Jupiter Gas Mine",
      "distance": 1000000000,
      "services": ["shop", "rest"]
    }
  }
}
```

### HTML Structure
```html
<div id="waypointModal" class="modal-overlay" style="display: none;">
    <div class="modal-backdrop"></div>
    <div class="cockpit-viewport">
        <div class="cockpit-scene">
            <div class="dashboard-scroll">
                <div class="dashboard" style="padding-top: 60px;">

                    <div class="panel-3d waypoint-panel">
                        <div class="panel-depth"></div>
                        <div class="panel-face">
                            <div class="panel-glass"></div>
                            <div class="stud-3d" style="top:12px;left:16px"></div>
                            <div class="stud-3d" style="top:12px;right:16px"></div>
                            <div class="stud-3d" style="bottom:12px;left:16px"></div>
                            <div class="stud-3d" style="bottom:12px;right:16px"></div>

                            <div class="waypoint-content">

                                <!-- Icon -->
                                <div class="waypoint-icon">🛰️</div>

                                <!-- Title -->
                                <h2 class="waypoint-title" id="waypointName"></h2>

                                <!-- Description -->
                                <div class="waypoint-desc">You've arrived at a waypoint station.</div>

                                <!-- Options -->
                                <div class="waypoint-options">

                                    <!-- Stop Option -->
                                    <div class="waypoint-option panel-3d" style="transform: translateZ(6px);">
                                        <div class="panel-depth"></div>
                                        <div class="panel-face" style="padding: 16px;">
                                            <div class="option-header">
                                                <span style="font-size: 24px;">⚓</span>
                                                <span style="font-family: 'Orbitron', sans-serif; font-size: 13px; font-weight: 700;">STOP AT STATION</span>
                                            </div>
                                            <div class="option-benefits">
                                                <div class="benefit-item">🏪 Shop for supplies</div>
                                                <div class="benefit-item">😴 Rest to restore vibes</div>
                                                <div class="benefit-item">🛠️ Repair hull damage</div>
                                            </div>
                                            <div class="option-warning">⚠️ Speed boost lost</div>
                                            <button id="btnStopAtWaypoint" class="btn-3d-main" style="margin-top: 16px;">
                                                <div class="btn-main-depth"></div>
                                                <div class="btn-main-face">
                                                    <span class="btn-text">Stop Here</span>
                                                </div>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Skip Option -->
                                    <div class="waypoint-option panel-3d" style="transform: translateZ(6px);">
                                        <div class="panel-depth"></div>
                                        <div class="panel-face" style="padding: 16px;">
                                            <div class="option-header">
                                                <span style="font-size: 24px;">⚡</span>
                                                <span style="font-family: 'Orbitron', sans-serif; font-size: 13px; font-weight: 700;">SKIP STATION</span>
                                            </div>
                                            <div class="option-benefits">
                                                <div class="benefit-item">⚡ Speed boost (2× velocity)</div>
                                                <div class="benefit-item">🚀 Faster arrival</div>
                                                <div class="benefit-item">💰 Save time</div>
                                            </div>
                                            <div class="option-warning">⚠️ No supplies or repairs</div>
                                            <button id="btnSkipWaypoint" class="btn-3d-main" style="margin-top: 16px;">
                                                <div class="btn-main-depth"></div>
                                                <div class="btn-main-face">
                                                    <span class="btn-text">Fly Past</span>
                                                </div>
                                            </button>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
```

### CSS Styles
```css
.waypoint-panel {
    transform: translateZ(20px);
}

.waypoint-content {
    padding: 24px 20px;
    text-align: center;
}

.waypoint-icon {
    font-size: 60px;
    margin-bottom: 16px;
}

.waypoint-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 18px;
    font-weight: 700;
    color: var(--text-bright);
    margin-bottom: 8px;
}

.waypoint-desc {
    font-size: 12px;
    color: var(--text-soft);
    margin-bottom: 24px;
}

.waypoint-options {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.waypoint-option {
    text-align: left;
}

.option-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    color: var(--amber);
}

.option-benefits {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 12px;
}

.benefit-item {
    font-size: 11px;
    color: var(--text-soft);
    padding-left: 4px;
}

.option-warning {
    font-family: 'Azeret Mono', monospace;
    font-size: 9px;
    color: var(--orange);
    text-align: center;
}
```

### JavaScript Handler
```javascript
showWaypointModal(waypoint) {
    const modal = document.getElementById('waypointModal');
    const nameEl = document.getElementById('waypointName');

    nameEl.textContent = waypoint.name;

    document.getElementById('btnStopAtWaypoint').onclick = () => {
        this.handleWaypointDecision('stop', waypoint.id);
    };

    document.getElementById('btnSkipWaypoint').onclick = () => {
        this.handleWaypointDecision('skip', waypoint.id);
    };

    modal.style.display = 'flex';
    this.uiState = 'waypoint';
}

async handleWaypointDecision(decision, waypointId) {
    document.getElementById('waypointModal').style.display = 'none';
    this.uiState = 'journey';

    const action = decision === 'stop' ? 'stop_at_waypoint' : 'skip_waypoint';
    await this.executeAction(action, { waypoint_id: waypointId });
}
```

---

## Implementation Checklist

### Phase 1: Event Modal (3 hours)
- [ ] Add HTML structure to app-v1.html
- [ ] Add CSS styles
- [ ] Implement showEventModal() in app.js
- [ ] Implement handleEventChoice() in app.js
- [ ] Test with mock event data
- [ ] Test with real API response

### Phase 2: Game Over Screen (2 hours)
- [ ] Add HTML structure
- [ ] Add CSS styles and animations
- [ ] Implement showGameOverScreen() in app.js
- [ ] Format stats display
- [ ] Test various death scenarios

### Phase 3: Victory Screen (2 hours)
- [ ] Add HTML structure
- [ ] Add CSS styles
- [ ] Implement showVictoryScreen() in app.js
- [ ] Implement confetti animation
- [ ] Test victory scenario

### Phase 4: Waypoint Modal (3 hours)
- [ ] Add HTML structure
- [ ] Add CSS styles
- [ ] Implement showWaypointModal() in app.js
- [ ] Implement handleWaypointDecision() in app.js
- [ ] Add action types to ActionManager
- [ ] Test stop/skip flows

### Phase 5: Integration (2 hours)
- [ ] Update executeAction() with outcome switch
- [ ] Add UI state management
- [ ] Handle modal z-index stacking
- [ ] Test all transitions
- [ ] Polish animations

**Total: 12 hours** (estimate)

---

## Related Documentation

- `API_VALIDATION.md` - Why these components are needed
- `MINI_APP_COMPONENTS.md` - Existing component catalog
- `API_ARCHITECTURE.md` - Data structures and outcome types
- `app-v1.html` - Current implementation

---

**Status:** Ready for implementation
**Next Step:** Begin Phase 1 (Event Modal)
