# Archived 2026-11-11: bot / Mini-App untangling

These documents described the Mini-App before it was made a full client of `game/GameService`.
They are kept for history; **do not rely on them**. Known stale points:

- They describe the Mini-App as a partial client (shop and status were placeholders) that needed the bot to start a game.
- They describe the API as `api/mini-app/` with an action map and outcome translation; it is now `api/game/` with none.
- Their data references were written against `mock-data.js` rather than the server (for example crew as `session.chad.members`, ETH in cents); the server never sent those shapes.
- Their line counts, `classes/` paths, `ActionManager` and `GameController` references predate the move to `game/` and `bot/`.

Current documentation: `docs/IMPLEMENTATION_GUIDE.md`, `docs/mini-app/README.md`, `api/game/README.md`.
