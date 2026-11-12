# Documentation Protocol

**Created:** 2026-02-28
**Last Updated:** 2026-02-28
**Purpose:** Standards and best practices for maintaining accurate, useful documentation

---

## Core Principles

### 1. Code is the Source of Truth

**Never trust documentation without verification.**

Documentation describes the code; code doesn't conform to documentation. When there's a discrepancy, the code is always right and the documentation must be updated.

### 2. Documentation Rots

Documentation becomes outdated the moment code changes. Combat this through:
- Verification timestamps
- Code references
- Regular audits
- Culture of updating docs immediately after code changes

### 3. Preserve History

Don't delete outdated documentation—archive it. Historical docs help understand:
- Design decisions
- Evolution of the system
- Why things changed
- What was tried and abandoned

---

## Documentation Types

### Active Documentation

**Location:** `docs/` (root level) and project root

**Purpose:** Current reference for developers and users

**Characteristics:**
- ✅ Describes current implementation
- ✅ Verified against code
- ✅ Updated immediately when code changes
- ✅ Includes verification timestamp
- ✅ Contains code references

**Examples:**
- `CLAUDE.md` - AI/Claude reference
- `README.md` - Project overview
- `DEPLOYMENT.md` - Deployment procedures
- `docs/ARCHITECTURE.md` - Current architecture
- `docs/GAME_MECHANICS_REVIEW.md` - Game design reference

#### Root vs docs/ Placement

**Root Directory Documentation**

**Purpose:** High-level project guidance and workflow documents

**Should be in root:**
- `README.md` - Project overview and quick start
- `CLAUDE.md` - AI assistant reference and conventions
- `DEPLOYMENT.md` - Deployment procedures
- `PRODUCTION_SETUP.md` - First-time production setup
- `IMPLEMENTATION_BACKLOG.md` - Planned features and enhancements
- `DOCS_PROTOCOL.md` - Documentation standards (this document)

**Should be in docs/:**
- Technical reference (ARCHITECTURE.md, SESSION_STATE.md)
- Development guides (DEVELOPMENT.md, testing docs)
- Game design docs (GAME_MECHANICS_REVIEW.md, GAME_VIEWS.md)
- API/system documentation (TELEGRAM_API.md, CODEBASE_STRUCTURE.md)
- User guides (USERS_GUIDE.md)

**Rule of thumb:** Root = workflow/process docs, docs/ = reference/technical docs

#### docs/ Subfolders

When documentation grows or related docs should be grouped, use subfolders:

**Purpose:** Organize related documentation into logical groups

**When to create a subfolder:**
- 3+ related documents on a specific topic
- Distinct subsystem or feature area
- Supporting materials (examples, demos, guides)

**Naming conventions:**
- Use lowercase with hyphens: `docs/testing/`, `docs/creative/`
- Keep names concise and descriptive
- Match the domain/feature they document

**Current subfolders:**
- `docs/archived/` - Historical documentation (required)
- `docs/testing/` - Testing guides and examples
- `docs/creative/` - Image generation prompts and aesthetic data

**Examples:**

```
docs/
├── ARCHITECTURE.md              # Core reference docs (flat)
├── SESSION_STATE.md
├── GAME_VIEWS.md
├── testing/                     # Testing-related docs
│   ├── LOCAL_TESTING.md
│   └── LOCAL_TESTER_DEMO.md
├── creative/                    # Creative assets
│   ├── IMAGE_SYSTEM.md
│   └── aesthetic_prompts/
└── archived/                    # Historical docs
    └── YYYY-MM-DD-project/
```

**Avoid:**
- Deep nesting (max 2 levels: `docs/subfolder/file.md`)
- Single-file folders (just keep in docs/ root)
- Redundant names (`docs/docs/` or `docs/documentation/`)

### Archived Documentation

**Location:** `docs/archived/YYYY-MM-DD-project-name/`

**Purpose:** Historical record of completed work

**Characteristics:**
- ✅ Organized by date and project
- ✅ Includes README explaining context
- ✅ Preserved for reference
- ✅ Clearly marked as historical
- ✅ Not updated (frozen in time)

**Examples:**
- `docs/archived/2026-02-28-separation-refactor/`
- `docs/archived/2026-02-27-waypoint-shops/`
- `docs/archived/2026-02-26-code-review/`

### Completion Summaries

**Location:** Project root (temporary, then archived)

**Purpose:** Document what was accomplished in a session/sprint

**Characteristics:**
- ✅ Summary of changes
- ✅ Before/after comparisons
- ✅ Impact assessment
- ✅ File change manifest
- ✅ Archived after review

**Examples:**
- `DOCUMENTATION_REORGANIZATION_COMPLETE.md`
- `GAME_MECHANICS_REWRITE_COMPLETE.md`

---

## Verification Standards

### Required for All Active Documentation

**1. Verification Timestamp**

Add to document header:
```markdown
**Last Verified Against Code:** 2026-02-28
```

Update this timestamp every time you verify the documentation still matches the code.

**2. Code References**

When describing mechanics, constants, or behavior, cite the source:

```markdown
**Fuel Consumption:** 5 units/day (flat rate)
- Source: `config.php` line 288: `FUEL_PER_DAY = 5`
- Implementation: `GameEngine.php` line 179
```

**3. Verification Log (for complex docs)**

Add a section documenting what was verified:

```markdown
## Code Verification Log

**Last Verified:** 2026-02-28

### Files Checked
- `config.php` (lines 279-400) - Constants and difficulty settings
- `GameEngine.php` (lines 152-456) - Core mechanics
- `ViewManager.php` (lines 273-274) - UI elements

### Verified Mechanics
- ✅ Fuel consumption rate
- ✅ Starting resources
- ✅ Event frequency
- ✅ Shop prices

### Not Yet Verified
- ⏳ Hunting success rates
- ⏳ Event outcome probabilities
```

---

## When Code Changes

### Immediate Actions Required

**1. Update Active Documentation**

When you change code that affects documented behavior:
- Update the relevant documentation in the same commit
- Update verification timestamp
- Add/update code references
- Update examples if affected

**2. Git Commit Message**

Include documentation changes in commit message:
```
Fix fuel consumption bug

Changed fuel consumption from chad-based to flat rate.

Code changes:
- GameEngine.php: Updated advanceDay() fuel calculation

Documentation changes:
- docs/GAME_MECHANICS_REVIEW.md: Updated fuel consumption description
- CLAUDE.md: Updated resource consumption section
```

### When Features Are Removed

**Never leave documentation describing removed features as if they still exist.**

**Process:**
1. **Update or Remove** - Rewrite sections to reflect current reality
2. **Archive if Substantial** - If feature was significant, archive the old docs
3. **Add Note** - Document when and why the feature was removed
4. **Update Related Docs** - Check for references in other files

**Example:**
When difficulty selection was removed (2026-02-27):
- ✅ Archived old Game Mechanics Review (described 3 difficulties)
- ✅ Created new document (describes single difficulty)
- ✅ Added archive note explaining removal
- ✅ Updated all references to difficulty system

---

## Archive Organization

### Folder Structure

```
docs/archived/
├── README.md (timeline index)
├── YYYY-MM-DD-project-name/
│   ├── README.md (project summary)
│   ├── [analysis docs]
│   ├── [implementation docs]
│   └── [completion docs]
```

### Naming Convention

**Format:** `YYYY-MM-DD-project-name`

**Examples:**
- `2026-02-28-separation-refactor`
- `2026-02-27-waypoint-shops`
- `2026-02-26-code-review`

**Rules:**
- Use ISO date format (YYYY-MM-DD) for chronological sorting
- Use kebab-case for project name
- Keep project name concise (2-4 words)
- Use completion date, not start date

### Archive README Template

Every archive folder should have a README.md:

```markdown
# [Project Name]

**Date:** YYYY-MM-DD
**Status:** ✅ Complete

---

## Overview

[1-2 paragraph summary of what this project accomplished]

---

## Documents in this Archive

- **[FILE.md]** - [Brief description]
- **[FILE2.md]** - [Brief description]

---

## Key Changes

- Change 1
- Change 2
- Change 3

---

## Impact

[How this changed the system]

---

**See:** [MAIN_DOC.md] for complete details
```

### Main Archive Index

Update `docs/archived/README.md` when adding new archives:

```markdown
## Project Timeline

### February 28, 2026

#### [2026-02-28-separation-refactor/](2026-02-28-separation-refactor/)
**Separation of Concerns Refactor** - Three-phase architectural refactor

**Files:** 6 documents
**Impact:** Clean architecture with single-responsibility classes
```

---

## Common Mistakes to Avoid

### ❌ Don't: Trust Old Documentation

```markdown
# Game Mechanics

Players select Easy, Normal, or Hard difficulty...
```

**Problem:** Feature was removed, document not updated.

**Solution:** Verify against code before referencing.

### ❌ Don't: Describe Implementation Without Code Reference

```markdown
Fuel consumption is 5 units per day per chad.
```

**Problem:** No way to verify, could be wrong.

**Solution:** Add code reference.

```markdown
Fuel consumption is 5 units per day (flat rate, not chad-based).
- Source: `config.php:288` `FUEL_PER_DAY = 5`
- Implementation: `GameEngine.php:179`
```

### ❌ Don't: Delete Historical Documentation

```markdown
# Deleting old docs because they're outdated
rm -rf docs/old_mechanics_review.md
```

**Problem:** Lost historical context and design decisions.

**Solution:** Archive it.

```markdown
# Archive outdated docs with context
mv docs/old_mechanics_review.md docs/archived/2026-02-26-code-review/MECHANICS_REVIEW_LEGACY.md
# Add archive note explaining why
```

### ❌ Don't: Leave Stale Verification Timestamps

```markdown
**Last Verified Against Code:** 2025-01-15
[... extensive documentation about game mechanics ...]
```

**Problem:** Timestamp is 13 months old, documentation is likely stale.

**Solution:** Re-verify or note that verification is needed.

### ❌ Don't: Document Features That Don't Exist

```markdown
The game offers three difficulty levels: Easy, Normal, and Hard.
```

**Problem:** If this feature was removed or never implemented, documentation is misleading.

**Solution:** Only document what actually exists in the code.

---

## Documentation Review Checklist

Before committing documentation, verify:

### Accuracy
- [ ] All technical details verified against code
- [ ] No references to removed features (unless marked as historical)
- [ ] Examples are current and working
- [ ] Numbers/statistics are correct

### Completeness
- [ ] Verification timestamp included
- [ ] Code references for key mechanics
- [ ] Related documentation updated
- [ ] Archive notes added if archiving

### Clarity
- [ ] Written for the target audience (developers, users, AI assistants)
- [ ] Technical jargon explained or linked
- [ ] Examples provided where helpful
- [ ] Structure is logical and scannable

### Maintainability
- [ ] Code references make it easy to verify later
- [ ] Verification log shows what was checked
- [ ] Unverified items clearly marked
- [ ] Easy to update when code changes

---

## Special Case: AI Assistant Documentation (CLAUDE.md)

`CLAUDE.md` is special—it's the primary reference for AI assistants working on the codebase.

### Extra Requirements

**1. Correctness is Critical**

AI assistants trust this file completely. Errors propagate into code changes.

**2. Update Immediately**

When you change patterns or conventions, update CLAUDE.md in the same commit.

**3. Include Decision Context**

Don't just say "do X", explain why:

```markdown
❌ Wrong:
- Use sendMessage() wrapper for all messages

✅ Better:
- **Always** use sendMessage() wrapper (not direct telegram->sendMessage)
- **Why:** Wrapper provides message tracking, owner stamping, and routing
```

**4. Provide Examples**

```markdown
**Good Example:**
```php
$this->sendMessage($chatId, $text, $imageUrl, $keyboard, $userId);
```

**Bad Example:**
```php
$this->telegram->sendMessage($chatId, $text); // ❌ Bypasses tracking
```
```

**5. Mark Sections Clearly**

Use emoji and bold headers so AI can quickly find relevant sections:

```markdown
## ✅ DO: Always Call answerCallbackQuery
## ❌ DON'T: Bypass sendMessage Wrapper
```

---

## Document Templates

### New Feature Documentation

```markdown
# [Feature Name]

**Created:** YYYY-MM-DD
**Status:** Active / In Development / Deprecated
**Last Verified:** YYYY-MM-DD

---

## Overview

[What this feature does and why it exists]

---

## How It Works

[Technical explanation with code references]

### Example

```code
[Working example]
```

---

## API / Interface

[Public methods, parameters, return values]

---

## Configuration

[Any constants or settings]

---

## Testing

[How to test this feature]

---

## Code Reference

| File | Purpose | Key Methods |
|------|---------|-------------|
| [file] | [purpose] | [methods] |

---

**Last Updated:** YYYY-MM-DD
```

### Code Review Summary

```markdown
# Code Review: [Area]

**Date:** YYYY-MM-DD
**Reviewer:** [Name/AI]
**Scope:** [What was reviewed]

---

## Summary

[High-level findings]

---

## Critical Issues ❌

| Issue | Location | Impact | Fix |
|-------|----------|--------|-----|
| [desc] | [file:line] | [impact] | [solution] |

---

## Recommendations 🔄

| Recommendation | Priority | Effort |
|----------------|----------|--------|
| [desc] | High/Med/Low | Low/Med/High |

---

## What Works Well ✅

- [Good pattern 1]
- [Good pattern 2]

---

**Status:** Complete / In Progress
```

---

## Maintenance Schedule

### Daily (During Active Development)

- [ ] Update docs immediately after code changes
- [ ] Verify related documentation still accurate

### Weekly

- [ ] Review verification timestamps
- [ ] Check for orphaned references
- [ ] Update archive index if needed

### Monthly

- [ ] Full documentation audit
- [ ] Re-verify complex documents against code
- [ ] Archive completion summaries
- [ ] Update docs/README.md index

### Before Each Release

- [ ] All active docs verified
- [ ] Archive outdated materials
- [ ] User-facing docs updated
- [ ] CLAUDE.md accuracy confirmed

---

## Git Commit Messages for Documentation

### Format

```
[doc type]: [brief description]

[Detailed description of changes]

[Files affected and why]

[If code-related: what triggered the doc change]
```

### Examples

**Good:**
```
docs: Update Game Mechanics Review for single-difficulty game

Removed all references to Easy/Normal/Hard difficulty selection
which was removed from the game on 2026-02-27. Rewrote document
to describe the single-difficulty game that actually exists.

Files changed:
- docs/GAME_MECHANICS_REVIEW.md (complete rewrite)
- docs/archived/2026-02-26-code-review/ (archived old version)

Triggered by: Discovery that old docs described removed features
```

**Bad:**
```
updated docs

fixed some stuff in the game mechanics file
```

---

## Emergency: Found Inaccurate Documentation

### Immediate Actions

**1. Assess Impact**
- How wrong is it?
- Who might have used it?
- What damage could result?

**2. Fix or Mark**

**If Minor:** Fix immediately
```markdown
~~Fuel consumption is chad-based~~
**CORRECTION (2026-02-28):** Fuel consumption is flat 5/day
```

**If Major:** Archive and rewrite
```bash
mv docs/WRONG_DOC.md docs/archived/2026-XX-XX-topic/WRONG_DOC_LEGACY.md
# Create new accurate doc
# Add archive note
```

**3. Notify (if needed)**
- Update CLAUDE.md if it referenced the bad doc
- Add note to related docs
- Consider adding to backlog for related verification

**4. Prevent Recurrence**
- Add verification log to document
- Add code references
- Schedule regular verification

---

## Success Metrics

Good documentation should:

✅ **Be Trustworthy** - Developers trust it without verification
✅ **Stay Current** - Reflects actual implementation
✅ **Be Discoverable** - Easy to find what you need
✅ **Be Maintainable** - Easy to update when code changes
✅ **Preserve History** - Design decisions are documented
✅ **Be Complete** - Answers common questions

---

## Summary: The Golden Rules

1. **Code is Truth** - Verify everything against actual implementation
2. **Update Immediately** - Change docs in the same commit as code
3. **Cite Your Sources** - Add file:line references
4. **Timestamp Everything** - "Last Verified: YYYY-MM-DD"
5. **Archive, Don't Delete** - Preserve history with context
6. **Organize by Date** - Use YYYY-MM-DD-project-name structure
7. **Write for Humans** - Clear, scannable, helpful
8. **Trust, then Verify** - But verify regularly

---

**Follow this protocol, and documentation becomes a reliable asset rather than a liability.**
