#!/usr/bin/env bash
#
# Builds dist/ — the exact set of files to upload to your host.
#
# The point of this script is that deleting admin.php stops being something you
# have to remember. It is excluded here, so every deploy is the shipped state by
# construction, and you never edit the live site by accident.
#
#   ./deploy.sh          build dist/
#   ./deploy.sh --check  build, then report what changed vs. the last build
#
set -euo pipefail

cd "$(dirname "$0")"

DB="data/portfolio.db"

if [ ! -f "$DB" ]; then
  echo "FAIL: $DB not found — nothing to deploy." >&2
  exit 1
fi

# WAL mode keeps recent writes in a sidecar file. Fold them into the .db so the
# single file you upload contains everything you just edited.
if command -v sqlite3 >/dev/null 2>&1; then
  sqlite3 "$DB" 'PRAGMA wal_checkpoint(TRUNCATE);' >/dev/null
else
  php -r 'require "config.php"; db()->exec("PRAGMA wal_checkpoint(TRUNCATE)");' >/dev/null
fi

rm -rf dist
mkdir -p dist

rsync -a \
  --exclude '/admin.php'      `# the admin itself — this is the deploy step` \
  --exclude '/deploy.sh'      \
  --exclude '/dist'           \
  --exclude '/tools'          `# dev scripts, incl. the importer and dev router` \
  --exclude '/migrations'     `# schema travels inside the .db, not as files` \
  --exclude '/PLAN.md'        \
  --exclude '/README.md'      \
  --exclude '/.git'           \
  --exclude '/.gitignore'     \
  --exclude '/data/.dev'      `# the dev marker AND the admin's token secret` \
  --exclude '*.db-wal'        \
  --exclude '*.db-shm'        \
  --exclude '.DS_Store'       \
  --exclude '*.HEIC'          `# camera originals: kept locally for re-cropping,` \
  --exclude '*.heic'          `# but browsers cannot display them` \
  --exclude '*.zip'           `# a backup zip in the web root would hand out the` \
  --exclude '/backups'        `# entire database as a single download` \
  --exclude '/certificates/_*' `# leading underscore = kept locally, not published` \
  ./ dist/

# Verify the shipped state rather than trust it.
#
# Note this checks that the FILE is absent — not that the string "admin.php" is
# absent from dist/. index.php still names the file in its guarded require, and
# always will; that reference is inert precisely because is_file() returns false.
fail=0
for forbidden in dist/admin.php dist/data/.dev dist/tools dist/migrations; do
  if [ -e "$forbidden" ]; then
    echo "FAIL: $forbidden leaked into dist/" >&2
    fail=1
  fi
done

# A backup archive contains the whole database. Anywhere under the web root it
# is a public download of everything, so treat one reaching dist/ as fatal
# rather than trusting the exclude above to have caught it.
if [ -n "$(find dist -name '*.zip' -print -quit)" ]; then
  echo "FAIL: a .zip leaked into dist/ — backups must never be deployed:" >&2
  find dist -name '*.zip' >&2
  fail=1
fi

for required in dist/index.php dist/data/portfolio.db dist/assets/css/site.css; do
  if [ ! -e "$required" ]; then
    echo "FAIL: $required missing from dist/" >&2
    fail=1
  fi
done

if ! grep -q "is_file(ADMIN_FILE)" dist/index.php; then
  echo "FAIL: the guarded require is missing from dist/index.php" >&2
  fail=1
fi

[ "$fail" -eq 0 ] || exit 1

echo "dist/ ready — $(find dist -type f | wc -l | tr -d ' ') files, $(du -sh dist | cut -f1)"
echo
echo "Upload the CONTENTS of dist/ to your web root. Then check:"
echo "  - the site loads and your content is there"
echo "  - there is no edit toolbar"
echo "  - yoursite.com/data/portfolio.db returns 403"
echo "  - yoursite.com/admin.php returns 404"
