# Mini-App Architecture Research

**Date:** 2026-03-07
**Status:** 🔍 Research / Discussion Phase

---

## Overview

Exploratory architectural analysis conducted during mini-app Phase 1-2 implementation to understand separation of concerns between the PHP Telegram bot and the HTML mini-app frontend.

These documents explore different approaches to unified abstraction layers and analyze how the mini-app's self-contained frontend pattern informs API design decisions.

**Outcome:** Research informed the decision to use a hybrid approach where both bot and mini-app share GameEngine/ActionManager logic but have separate presentation layers (ViewManager for bot, JavaScript UI for mini-app).

---

## Documents in this Archive

- **ARCHITECTURE_ANALYSIS.md** - Deep dive into bot vs mini-app data flow patterns, identifies architectural challenges in creating unified abstraction layer
- **FRONTEND_PATTERNS_ANALYSIS.md** - Analysis of mini-app frontend patterns, documents self-sufficient presentation layer approach

---

## Key Findings

1. **Bot uses three-layer architecture:** GameEngine (logic) → ActionManager (orchestration) → ViewManager (presentation)
2. **Mini-app only needs two layers:** GameEngine/ActionManager (logic) → JavaScript UI (presentation)
3. **ActionManager already provides needed data:** `next_view` field contains outcome classification
4. **No new abstraction layer needed:** Can expose ActionManager data directly via API
5. **Separation is intentional:** ViewManager (Telegram-specific) vs. JavaScript UI (web-specific) serve different needs

---

## Impact

These insights shaped the final API design documented in `docs/mini-app/API_ARCHITECTURE.md`:
- Action endpoints expose ActionManager outcomes directly
- Mini-app builds missing UI components (event modal, game over, etc.)
- No "unified abstraction layer" - each presentation layer optimized for its platform

---

## Related Documentation

- **Active:** `docs/mini-app/API_ARCHITECTURE.md` - Final API design decisions
- **Active:** `docs/mini-app/API_VALIDATION.md` - Architecture validation results
- **Active:** `docs/mini-app/MINI_APP_V1_IMPLEMENTATION.md` - Implementation plan

---

**Context:** These were exploratory discussions during Phase 1-2 development. The conclusions informed the current implementation but the documents themselves remain in discussion phase (not implemented).
