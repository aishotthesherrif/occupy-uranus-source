# CLAUDE.md Content Audit

**Date:** 2026-02-28
**Purpose:** Identify what content belongs in CLAUDE.md (instructions) vs reference docs

---

## Audit Criteria

**CLAUDE.md should contain:**
- ✅ Rules and conventions ("Do this, not that")
- ✅ Common mistakes with examples
- ✅ Critical patterns to follow
- ✅ Quick pointers to detailed docs
- ✅ "How to work here" instructions

**CLAUDE.md should NOT contain:**
- ❌ Detailed architecture explanations
- ❌ Complete reference material
- ❌ Step-by-step procedures (unless critical)
- ❌ Content duplicated from other docs
- ❌ Comprehensive lists

---

## Section-by-Section Analysis

### ✅ KEEP (Instructions/Rules)

| Lines | Section | Why Keep | Notes |
|-------|---------|----------|-------|
| 1-16 | **Header & Brief Description** | Quick context for Claude | Trim to 2-3 sentences max |
| 94-101 | **Message Handling Pattern** | Critical instruction with example | Perfect format |
| 170-268 | **Conventions (Laws of Codebase)** | PURE INSTRUCTIONS - this is perfect! | No changes needed |
| 345-418 | **Common Pitfalls** | Exactly what CLAUDE.md should be | Keep all 5 examples |

**Subtotal: ~180 lines of actual instructions** ✅

---

### ❌ MOVE (Reference Material)

| Lines | Section | Move To | Reason |
|-------|---------|---------|--------|
| 19-49 | **Dependency Tree** | `docs/CODEBASE_STRUCTURE.md` | File listing, not instructions |
| 53-66 | **Data Flow** | `docs/ARCHITECTURE.md` | Architecture explanation |
| 70-83 | **Architecture - Class Table** | Keep abbreviated version | Quick ref useful, but link to full doc |
| 84-92 | **Key Files List** | `docs/CODEBASE_STRUCTURE.md` | Reference material |
| 105-167 | **Session State Reference** | `docs/SESSION_STATE.md` (new) | Detailed data structure reference |
| 271-288 | **Telegram API Constraints** | `docs/TELEGRAM_API.md` (new) | Comprehensive constraint list |
| 422-448 | **Testing Locally** | `docs/DEVELOPMENT.md` (new) | Setup procedures |
| 452-560 | **Deployment** | Keep pointer only | Already in DEPLOYMENT.md |
| 563-583 | **Quick Reference** | Partial keep | Constants useful, file sizes not needed |

**Subtotal: ~330 lines to move** ❌

---

### 🔄 SIMPLIFY (Duplicates/Pointers)

| Lines | Section | Action | Reason |
|-------|---------|--------|--------|
| 291-310 | **Known Tech Debt** | Replace with pointer | Duplicates IMPLEMENTATION_BACKLOG.md |
| 313-341 | **Game Design Status** | Replace with pointer | Duplicates GAME_MECHANICS_REVIEW.md |
| 596-612 | **Code Review Status** | Simplify drastically | Meta info, not instructions |

**Subtotal: ~60 lines to simplify** 🔄

---

## Recommendations

### Priority 1: Immediate Changes (Quick Wins)

**Remove Duplicates:**
1. Delete lines 291-310 (Known Tech Debt) → Replace with: "See `IMPLEMENTATION_BACKLOG.md`"
2. Delete lines 313-341 (Game Design Status) → Replace with: "See `GAME_MECHANICS_REVIEW.md`"
3. Delete lines 452-560 (Deployment section) → Replace with: "See `DEPLOYMENT.md`"

**Impact:** Reduces CLAUDE.md from 617 lines → ~500 lines with zero loss of instructions

---

### Priority 2: Extract Reference Material

**Create new reference docs:**

#### `docs/SESSION_STATE.md` (NEW)
- Move lines 105-167 (Session State Reference)
- Add code references for each field
- Include verification timestamp
- Complete documentation of all session fields

#### `docs/CODEBASE_STRUCTURE.md` (NEW)
- Move lines 19-49 (Dependency Tree)
- Move lines 84-92 (Key Files List)
- Add descriptions of each major file's purpose
- Include line counts and responsibilities

#### `docs/TELEGRAM_API.md` (NEW)
- Move lines 271-288 (Telegram API Constraints)
- Expand with examples and edge cases
- Document all known Telegram behaviors
- Include troubleshooting section

#### `docs/DEVELOPMENT.md` (NEW)
- Move lines 422-448 (Testing Locally)
- Add debugging procedures
- Include common development workflows
- Link to related docs

**Impact:** CLAUDE.md reduces to ~350 lines, all other content preserved in proper locations

---

### Priority 3: Optimize What Remains

**Restructure CLAUDE.md to be:**

```markdown
# CLAUDE.md - Working Instructions

## Overview
[2-3 sentence project description]

## Quick Navigation
- Architecture → docs/ARCHITECTURE.md
- Session data → docs/SESSION_STATE.md
- Telegram API → docs/TELEGRAM_API.md
- Development → docs/DEVELOPMENT.md
- Deployment → DEPLOYMENT.md
- Backlog → IMPLEMENTATION_BACKLOG.md

## Critical Patterns

### Message Handling
[Keep existing example - lines 94-101]

### Class Responsibilities
[Keep abbreviated table with "Must NOT Do" column]
[Link to full ARCHITECTURE.md]

## Conventions (Laws of the Codebase)
[Keep ALL of lines 170-268 - perfect as-is]

## Common Pitfalls
[Keep ALL of lines 345-418 - perfect as-is]

## Quick Reference
[Keep essential constants only]
- TOTAL_DISTANCE = 2.8B km
- TRAVEL_SPEED = 20M km/day
- FUEL_PER_DAY = 5
- FOOD_PER_CREW = 3

[Link to config.php for complete list]
```

**Result:** ~250 lines of pure, actionable instructions

---

## Comparison: Before vs After

### Current CLAUDE.md (617 lines)
- ✅ 180 lines of instructions (29%)
- ❌ 330 lines of reference material (54%)
- 🔄 60 lines of duplicates (10%)
- ℹ️ 47 lines of headers/spacing (7%)

### Proposed CLAUDE.md (~250 lines)
- ✅ 180 lines of instructions (72%)
- ✅ 40 lines of quick nav/context (16%)
- ✅ 30 lines of essential quick ref (12%)
- ❌ 0 lines of duplicated content (0%)

**Improvement:** 72% instructions (up from 29%)

---

## Implementation Plan

### Phase 1: Remove Duplicates (5 minutes)
1. Delete Known Tech Debt section → add pointer
2. Delete Game Design Status section → add pointer
3. Delete Deployment section → add pointer

### Phase 2: Create New Docs (30 minutes)
1. Create `docs/SESSION_STATE.md` with moved content
2. Create `docs/CODEBASE_STRUCTURE.md` with moved content
3. Create `docs/TELEGRAM_API.md` with moved content
4. Create `docs/DEVELOPMENT.md` with moved content
5. Update each with verification timestamps and code references

### Phase 3: Restructure CLAUDE.md (20 minutes)
1. Add "Quick Navigation" section at top
2. Trim header to 2-3 sentences
3. Simplify architecture table (keep "Must NOT Do" emphasis)
4. Keep Conventions and Common Pitfalls unchanged
5. Reduce Quick Reference to essentials only

### Phase 4: Update Cross-References (10 minutes)
1. Update docs/README.md with new docs
2. Update ARCHITECTURE.md to reference SESSION_STATE.md
3. Verify all pointers work

**Total Time Estimate:** ~65 minutes

---

## Benefits

### For Claude/AI Assistants
- ✅ Faster to parse (60% shorter)
- ✅ Higher signal-to-noise ratio
- ✅ Clear instructions vs reference distinction
- ✅ Easy to find "how to work here" rules

### For Human Developers
- ✅ Clear documentation structure
- ✅ Reference material properly organized
- ✅ Easy to update (single source of truth)
- ✅ Better onboarding experience

### For Maintainability
- ✅ No duplicate content across files
- ✅ Each doc has single purpose
- ✅ Changes update one place only
- ✅ Follows DOCS_PROTOCOL standards

---

## Risk Assessment

**Low Risk:**
- ❌ No code changes
- ❌ No gameplay changes
- ❌ No breaking changes
- ✅ All content preserved (just moved)
- ✅ Can be done incrementally
- ✅ Easy to rollback (git)

**Validation:**
- Test: Can Claude still understand project structure? ✅ (Quick Nav section)
- Test: Are critical rules still prominent? ✅ (Conventions section unchanged)
- Test: Is reference material still accessible? ✅ (New dedicated docs)

---

## Decision Point

**Option A: Do all 3 phases** (~65 min)
- Full restructure
- Maximum clarity
- Best long-term solution

**Option B: Just Phase 1** (~5 min)
- Quick wins only
- Remove duplicates
- Some improvement immediately

**Option C: Phases 1 + 2** (~35 min)
- Remove duplicates + create reference docs
- Don't restructure CLAUDE.md yet
- Significant improvement, less disruption

**Recommendation:** Option C (Phases 1 + 2)
- Gets most benefit (removes duplication, creates proper reference docs)
- Doesn't touch the instruction sections that work well
- Leaves Phase 3 for when you want to optimize further

---

## Next Steps

If approved, I will:
1. Create this audit report for your review
2. Wait for your decision (A, B, or C)
3. Execute the chosen option
4. Create completion summary
5. Commit changes with clear message

---

**Audit Status:** ✅ Complete
**Recommendation:** Option C (remove duplicates + create reference docs)
**Estimated Impact:** CLAUDE.md: 617 lines → ~450 lines (27% reduction, 0% instruction loss)
