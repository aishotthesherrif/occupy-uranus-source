# Journey View - Final Specification (Option A)

## Design Questions to Resolve

### 1. **Header Format**
Current proposal:
```
📅 DAY 42 — Mission to Uranus
```

**Questions:**
- Keep "Mission to Uranus" or just "DAY 42"?
- Should difficulty show here? `📅 DAY 42 | 🟡 Normal`
- What about status? `📅 DAY 42 | 📍 In Space`

**Options:**
```
A) 📅 DAY 42 — Mission to Uranus
B) 📅 DAY 42 | 🟡 Entrepreneur (Normal)
C) 📅 DAY 42 | 📍 In Space
D) 🚀 DAY 42 — Journey to Uranus
E) 📅 Day 42
```

---

### 2. **Progress Section**
Current proposal:
```
🚀 Distance Today: 20M km
📍 Progress: 840M km / 2.8B km (30%)
⏳ ETA: 98 days
```

**Questions:**
- Show "Distance Today" every time, or only after Continue action?
- Should we show location/waypoint name? "📍 Location: Asteroid Belt"
- Line order - what's most important to see first?

**Options:**
```
A) As shown above (distance today first)

B) Progress first, then context:
📍 Progress: 840M / 2,800M km (30%)
🚀 Traveled Today: 20M km
⏳ ETA: 98 days remaining

C) With location:
📍 Location: Asteroid Belt (840M km)
🎯 To Uranus: 1,960M km (30% complete)
🚀 Speed: 20M km/day | ⏳ 98 days

D) Ultra-minimal:
📍 840M / 2,800M km (30%) | ⏳ 98 days
```

---

### 3. **Gravitational Assist Banner**
**Where does it go?**

**Options:**
```
A) After header, before progress:
📅 DAY 42 — Mission to Uranus
⚡ GRAVITATIONAL ASSIST ACTIVE — 2× Speed Boost
🚀 Distance Today: 40M km
...

B) In progress section:
📅 DAY 42 — Mission to Uranus
🚀 Distance Today: 40M km (⚡ 2× BOOST)
📍 Progress: 880M / 2,800M km (31%)
...

C) After progress section:
📅 DAY 42 — Mission to Uranus
🚀 Distance Today: 40M km
📍 Progress: 880M km / 2.8B km (31%)
⚡ Speed Boost: 2× ACTIVE
⏳ ETA: 49 days
...

D) Minimal inline:
📅 DAY 42 — Mission to Uranus
🚀 Distance: 40M km today (⚡2×) | ⏳ 49 days
📍 880M / 2,800M km (31%)
...
```

---

### 4. **Resources Section**
Current proposal:
```
⚠️ Critical Resources:
⛽ Fuel: 245 units  |  🍖 Food: 180 units
🛡️ Hull: 78%       |  👥 Chad: 5/5  |  😊 Morale: 65%
```

**Questions:**
- Always show "⚠️ Critical Resources:" header, or only when resources ARE critical?
- What defines "critical"? (Below 20%? Below certain days remaining?)
- Two lines or three?
- Show actual values or days remaining? "⛽ Fuel: 12 days"

**Options:**
```
A) Always show header:
⚠️ Critical Resources:
⛽ Fuel: 245  |  🍖 Food: 180  |  🛡️ Hull: 78%
👥 Chad: 5/5  |  😊 Morale: 65%

B) Conditional header (only if any resource < 20%):
⚠️ RESOURCE ALERT: Fuel critically low!
⛽ Fuel: 45  |  🍖 Food: 180  |  🛡️ Hull: 78%
👥 Chad: 5/5  |  😊 Morale: 65%

C) Simple header always:
📦 Resources:
⛽ Fuel: 245  |  🍖 Food: 180  |  🛡️ Hull: 78%
👥 Chad: 5/5  |  😊 Morale: 65%

D) No header, three lines:
⛽ Fuel: 245 units  |  🍖 Food: 180 units
🛡️ Hull: 78%  |  👥 Chad: 5/5
😊 Morale: 65%

E) Days remaining format:
📦 Supplies:
⛽ Fuel: 245 units (12 days)  |  🍖 Food: 180 units (12 days)
🛡️ Hull: 78%  |  👥 Chad: 5/5  |  😊 Morale: 65%

F) Ultra-compact (one line):
📦 ⛽245 | 🍖180 | 🛡️78% | 👥5/5 | 😊65%
```

---

### 5. **Flash Messages** (Event outcomes, Rest results, Hunt results)
**Where do they appear?**

**Options:**
```
A) Top of view (before header):
──────────────────────────
🚨 EVENT RESOLVED
You chose to navigate carefully through the asteroid field.
Lost 10 fuel but avoided damage.
──────────────────────────

📅 DAY 42 — Mission to Uranus
...

B) After header, before progress:
📅 DAY 42 — Mission to Uranus

✅ Event Resolved: Navigated asteroid field safely
Lost: ⛽ -10 fuel

🚀 Distance Today: 20M km
...

C) As banner after header:
📅 DAY 42 — Mission to Uranus
━━━━━━━━━━━━━━━━━━━━
⚡ ASTEROID FIELD NAVIGATED
Lost 10 fuel, avoided damage
━━━━━━━━━━━━━━━━━━━━
🚀 Distance Today: 20M km
...

D) At bottom, before buttons:
📅 DAY 42 — Mission to Uranus
🚀 Distance Today: 20M km
📍 Progress: 840M / 2.8B km (30%)
⏳ ETA: 98 days

📦 Resources:
⛽ Fuel: 235 (-10)  |  🍖 Food: 180
🛡️ Hull: 78%  |  👥 Chad: 5/5  |  😊 Morale: 65%

💬 You navigated the asteroid field carefully.
```

---

### 6. **Waypoint/Location Context**
**When at a waypoint, should we show different information?**

**Options:**
```
A) Same format, no special treatment

B) Replace progress line with waypoint:
📅 DAY 42 — Mission to Uranus
📍 Arrived at: Ceres Station (414M km)
⏳ ETA to Uranus: 120 days
...

C) Add waypoint as additional line:
📅 DAY 42 — Mission to Uranus
🛰️ At Waypoint: Ceres Station
🚀 Distance Today: 20M km
📍 Progress: 414M / 2,800M km (15%)
...

D) Waypoint banner:
📅 DAY 42 — Mission to Uranus
━━━━━━━━━━━━━━━━━━━━
🛰️ ARRIVED: CERES STATION
━━━━━━━━━━━━━━━━━━━━
📍 Progress: 414M / 2,800M km (15%)
...
```

---

### 7. **Footer/Action Hint**
**Should we add text before buttons?**

**Options:**
```
A) No footer text (buttons are self-explanatory)

B) Simple hint:
What will you do?

C) Context-aware hint:
Ready to continue your journey?
(or "Rest here to restore morale?" if at waypoint)

D) Tutorial-style:
💡 Click Continue to advance 1 day
```

---

## Proposed Final Format (For Review)

Based on typical gameplay flow, here's my suggestion:

### Normal Day (In Space)
```
📅 DAY 42 — Journey to Uranus

📍 840M / 2,800M km (30%) | ⏳ 98 days
🚀 Traveled: 20M km today

📦 Resources:
⛽ Fuel: 245  |  🍖 Food: 180  |  🛡️ Hull: 78%
👥 Chad: 5/5  |  😊 Morale: 65%

[Buttons]
```

### With Gravitational Assist
```
📅 DAY 42 — Journey to Uranus

⚡ GRAVITATIONAL ASSIST — 2× Speed Boost

📍 880M / 2,800M km (31%) | ⏳ 49 days
🚀 Traveled: 40M km today

📦 Resources:
⛽ Fuel: 245  |  🍖 Food: 180  |  🛡️ Hull: 78%
👥 Chad: 5/5  |  😊 Morale: 65%

[Buttons]
```

### At Waypoint
```
📅 DAY 42 — Journey to Uranus

🛰️ At Waypoint: Ceres Station
📍 414M / 2,800M km (15%) | ⏳ 120 days

📦 Resources:
⛽ Fuel: 245  |  🍖 Food: 180  |  🛡️ Hull: 78%
👥 Chad: 5/5  |  😊 Morale: 65%

[Buttons including Shop/Rest]
```

### With Flash Message (After Event)
```
📅 DAY 42 — Journey to Uranus

✅ Asteroid field navigated safely!
Lost: ⛽ -10 fuel

📍 840M / 2,800M km (30%) | ⏳ 98 days
🚀 Traveled: 20M km today

📦 Resources:
⛽ Fuel: 235  |  🍖 Food: 180  |  🛡️ Hull: 78%
👥 Chad: 5/5  |  😊 Morale: 65%

[Buttons]
```

### With Critical Resource Warning
```
📅 DAY 42 — Journey to Uranus

📍 840M / 2,800M km (30%) | ⏳ 98 days
🚀 Traveled: 20M km today

⚠️ WARNING: Fuel critically low!
⛽ Fuel: 45  |  🍖 Food: 180  |  🛡️ Hull: 78%
👥 Chad: 5/5  |  😊 Morale: 65%

[Buttons]
```

---

## Decision Points

Please decide on:

1. **Header format** - Which option A-E?
2. **Progress section** - Which option A-D?
3. **Resource section** - Which option A-F?
4. **Flash message placement** - Which option A-D?
5. **Waypoint treatment** - Which option A-D?
6. **Critical resource logic** - What threshold triggers warning?
7. **Footer text** - Include or omit?
8. **Overall length target** - Maximum lines acceptable?

Once you choose, I'll implement the exact formatter!
