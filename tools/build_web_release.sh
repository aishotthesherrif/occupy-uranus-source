#!/bin/bash
#
# Build the browser-only release of Occupy Uranus into a directory:
# the Mini-App, its game API and the game. No Telegram bot, no webhook, no images, no bot token.
# Web players sign in with a captain name (honor system); Telegram sign-in stays off
# because the release has no bot token.
#
# The directory is the working tree of the release repository that the web host clones
# (see docs/DOCKER.md, "Web release"). Everything in it except .git is replaced.
#
# Usage: tools/build_web_release.sh <out-dir>
#
set -euo pipefail

OUT=${1:?usage: tools/build_web_release.sh <out-dir>}
ROOT=$(cd "$(dirname "$0")/.." && pwd)

mkdir -p "$OUT"
OUT=$(cd "$OUT" && pwd)
case "$OUT" in "$ROOT"|"$ROOT"/*) echo "❌ out-dir must be outside the repo" >&2; exit 1 ;; esac
find "$OUT" -mindepth 1 -maxdepth 1 ! -name .git -exec rm -rf {} +

cd "$ROOT"
rsync -a --relative \
    config.php .htaccess index.html \
    game/ api/game/ \
    mini-app/app-v1.html mini-app/js/ \
    "$OUT/"
rm -f "$OUT/api/game/README.md"

# Static game content only; leaderboards, saves and backups are made on the server
mkdir -p "$OUT/data"
for f in data/*.json; do
    case "$(basename "$f")" in game_state*|leaderboards*|image_cache*) continue ;; esac
    cp "$f" "$OUT/data/"
done

# No bot token in the release: Telegram sign-in is off, web sign-in still works
sed -E -i.bak "s/(getenv\('TELEGRAM_BOT_TOKEN'\) \?: )'[^']*'/\1''/" "$OUT/config.php"
rm "$OUT/config.php.bak"
if grep -rqE '[0-9]{8,10}:[A-Za-z0-9_-]{30,}' "$OUT" --exclude-dir=.git; then
    echo "❌ something that looks like a bot token is still in the release" >&2
    exit 1
fi

# Runtime files are written next to the code (logs/, data/leaderboards.json);
# keep them out of the release repo and out of the browser
mkdir -p "$OUT/logs"
printf 'Require all denied\n' > "$OUT/logs/.htaccess"
cat > "$OUT/.gitignore" <<'EOF'
logs/*
!logs/.htaccess
data/leaderboards.json
data/game_state*.json
data/backups/
media/
EOF

cat > "$OUT/README.md" <<'EOF'
# Occupy Uranus (web release)

Built by `tools/build_web_release.sh` in the main repository. Don't edit here.
Browser-only: the Mini-App, its API and the game. No Telegram bot, no bot token.
Players sign in with a captain name (honor system).
EOF

find "$OUT" -name '*.php' -not -path '*/.git/*' -print0 | xargs -0 -n1 php -l > /dev/null
echo "✅ Web release built in $OUT ($(du -sh --exclude=.git "$OUT" 2>/dev/null | cut -f1 || du -sh "$OUT" | cut -f1))"
