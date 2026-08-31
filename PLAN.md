# Portfolio Website — Implementation Plan

**Goal:** a database-driven portfolio you edit through on-page buttons in your dev
environment. Ship it by deleting one file: the buttons vanish, every change you made
stays, because the content lives in SQLite — not in the admin file.

---

## 1. The core mechanic

Everything in this plan exists to serve one line, at the bottom of every page:

```php
<?php
// The ONLY reference to admin anywhere outside admin.php itself.
if (is_file(__DIR__ . '/admin.php')) require __DIR__ . '/admin.php';
?>
```

* `admin.php` present  → the toolbar, edit pencils, modals, uploader and its POST
  handlers all load.
* `admin.php` deleted  → `is_file()` is false, nothing loads. No admin markup, no
  admin JavaScript, no admin endpoints on the server at all. The page renders from
  `data/portfolio.db` exactly as before.

Two rules make this work, and they are the rules the whole build must obey:

1. **Content never lives in `admin.php`.** It lives in SQLite. `admin.php` only ever
   writes to the DB and to the media folders.
2. **The public site never reads anything from `admin.php`.** Rendering helpers live
   in `lib/`. If it is needed to display the page, it is not in the admin file.

Note `require` is used, not `include`: if the file exists but is broken, you want a
loud failure in dev, not a silent half-loaded toolbar. The `is_file()` guard is what
makes the absence silent — a bare `include` of a missing file emits a PHP warning that
would show up on your live site.

---

## 2. Stack

| Piece    | Choice                        | Why |
|----------|-------------------------------|-----|
| Language | PHP 8.4                       | `is_file()` + `require` *is* the delete-to-ship mechanic, for free |
| Database | SQLite via PDO (`pdo_sqlite`) | Single file, zero server, copy it to deploy |
| Frontend | Server-rendered HTML + vanilla JS | No build step, no `node_modules` to deploy |
| Hosting  | Any PHP shared host           | Upload files over SFTP; no process to keep alive |

Not currently installed. Setup is Phase 0 below.

---

## 3. Directory layout

```
myPortfolio/
├── index.php              # public page — renders entirely from the DB
├── admin.php              # ← THE FILE. Delete this to ship. Self-contained.
├── config.php             # paths, constants, opens the DB
├── lib/
│   ├── db.php             # PDO connection, WAL mode, migration runner
│   ├── content.php        # setting()/rows() readers used by templates
│   └── render.php         # e(), img(), section(), editable-attr helpers
├── partials/
│   ├── hero.php  about.php  skills.php  projects.php
│   ├── certificates.php   experience.php   contact.php
├── assets/
│   ├── css/site.css
│   └── js/site.js         # public-only JS (nav, scroll, lightbox)
├── pictures/              # your photos, project shots, logos
│   └── .htaccess          # disables PHP execution here
├── certificates/          # your certificate PDFs / images
│   └── .htaccess          # disables PHP execution here
├── data/
│   ├── portfolio.db       # ← all your content
│   └── .htaccess          # deny all — nobody downloads your DB over HTTP
├── migrations/
│   ├── 001_init.sql
│   └── 002_*.sql
├── deploy.sh              # builds ./dist without admin.php
└── README.md
```

`pictures/` and `certificates/` sit at the web root because they are meant to be
publicly linkable. `data/` must not be. The `.htaccess` files are not optional —
without the one in `certificates/`, an uploaded `.php` disguised as a cert is remote
code execution; without the one in `data/`, anyone can fetch your entire database at
`yoursite.com/data/portfolio.db`.

---

## 4. Database schema

Two shapes of content, handled two ways.

### 4.1 Singletons → `settings` (key/value with UI metadata)

Every one-off piece of text — site title, hero heading, the about paragraph, your
email, accent colour. One table, and the metadata columns are what let `admin.php`
generate the correct input widget automatically instead of you hand-writing a form
per field.

```sql
CREATE TABLE settings (
  key      TEXT PRIMARY KEY,     -- 'hero.title', 'contact.email'
  value    TEXT NOT NULL DEFAULT '',
  type     TEXT NOT NULL DEFAULT 'text',  -- text|textarea|richtext|color|image|url|bool
  label    TEXT NOT NULL,        -- human label shown in the admin panel
  section  TEXT NOT NULL,        -- groups fields in the admin panel
  sort     INTEGER NOT NULL DEFAULT 0
);
```

Adding a new editable field later = one `INSERT` in a migration + one attribute in a
template. No admin code changes. This is the single most important design decision in
the plan: it is why "buttons to change everything" stays cheap as the site grows.

### 4.2 Collections → one table each

```sql
CREATE TABLE projects (
  id INTEGER PRIMARY KEY, title TEXT NOT NULL, slug TEXT UNIQUE,
  summary TEXT, description TEXT, media_id INTEGER REFERENCES media(id),
  repo_url TEXT, live_url TEXT, tags TEXT,          -- comma-separated
  sort INTEGER DEFAULT 0, visible INTEGER DEFAULT 1,
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE certificates (
  id INTEGER PRIMARY KEY, title TEXT NOT NULL, issuer TEXT,
  issue_date TEXT, expiry_date TEXT,                -- ISO 'YYYY-MM-DD'
  credential_id TEXT, credential_url TEXT,
  file_path TEXT,                                   -- 'certificates/aws-saa.pdf'
  thumb_path TEXT,                                  -- 'pictures/thumbs/aws-saa.png'
  sort INTEGER DEFAULT 0, visible INTEGER DEFAULT 1
);

CREATE TABLE skills (
  id INTEGER PRIMARY KEY, name TEXT NOT NULL, category TEXT,
  level INTEGER DEFAULT 0, icon TEXT,
  sort INTEGER DEFAULT 0, visible INTEGER DEFAULT 1
);

CREATE TABLE experience (
  id INTEGER PRIMARY KEY, role TEXT NOT NULL, org TEXT, location TEXT,
  start_date TEXT, end_date TEXT,                   -- NULL end_date = "Present"
  bullets TEXT,                                     -- one per line
  sort INTEGER DEFAULT 0, visible INTEGER DEFAULT 1
);

CREATE TABLE media (
  id INTEGER PRIMARY KEY, path TEXT NOT NULL UNIQUE, -- relative to web root
  kind TEXT NOT NULL,                                -- 'picture' | 'certificate'
  alt TEXT, mime TEXT, bytes INTEGER, width INTEGER, height INTEGER,
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE sections (                              -- reorder/hide whole sections
  key TEXT PRIMARY KEY, title TEXT, enabled INTEGER DEFAULT 1, sort INTEGER DEFAULT 0
);

CREATE TABLE schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT);
```

`sort` + `visible` on every collection is what powers drag-to-reorder and the
show/hide eye toggle without any per-table special casing.

### 4.3 Migrations

`lib/db.php` runs unapplied files from `migrations/` in filename order on every boot
and records them in `schema_migrations`. Cheap, and it means the schema change you
make locally travels with the `.db` file when you deploy — nothing to run on the host.

---

## 5. How the edit buttons attach

Templates never contain admin markup or `if (admin)` branches. They emit inert data
attributes that mean nothing to a browser when `admin.php` is gone:

```php
<h1 data-edit="setting:hero.title"><?= e(setting('hero.title')) ?></h1>

<article data-row="projects:<?= $p['id'] ?>">
  <h3 data-edit="row:projects:<?= $p['id'] ?>:title"><?= e($p['title']) ?></h3>
</article>

<div data-collection="projects"> ... </div>
```

`admin.php` ships a script that scans for `[data-edit]`, `[data-row]` and
`[data-collection]` on load and injects a pencil next to each, an add/delete/reorder
control per collection, and a floating toolbar. Delete the file and those attributes
are ~40 bytes of dead HTML that nothing reads.

The upside: **making a new thing editable is adding one attribute.** No admin file
changes.

### The single save endpoint

`admin.php` handles its own POSTs (`admin.php?action=save|create|delete|reorder|upload`).
`save` takes the same `target` string the DOM carries:

```
target=setting:hero.title            value=…
target=row:projects:12:title         value=…
```

which is parsed against a **hardcoded allowlist of editable table→columns** inside
`admin.php`. That allowlist is the security boundary — without it, `target` is
arbitrary SQL column access. Every write is a prepared statement; table and column
names come from the allowlist, never from the request.

---

## 6. What the admin actually gives you

* **Floating toolbar** — Edit-mode toggle, Sections, Media, Theme, Backup, Deploy check.
* **Inline text editing** — click a pencil, edit in place, saves on blur.
* **Collections** — add / edit / delete / drag-reorder / show-hide for projects,
  certificates, skills, experience.
* **Media manager** — drag-drop upload routed to `pictures/` or `certificates/` by
  kind, set alt text, delete, reuse. Auto-generates a thumbnail for cert PDFs.
* **Theme panel** — colours, fonts, spacing, default dark/light. Stored in `settings`,
  emitted by `lib/render.php` as CSS custom properties in a `<style>` block, so the
  theme survives deletion of the admin file like everything else.
* **Backup** — one button, zips `data/portfolio.db` + `pictures/` + `certificates/`.
* **Deploy check** — runs the safety greps from §8 and tells you what to upload.

---

## 7. Security

The admin is dev-only, but plan for the day you forget to delete it.

1. **Localhost gate.** First lines of `admin.php`: return immediately unless
   `$_SERVER['REMOTE_ADDR']` is `127.0.0.1` / `::1`. On a real host that is never true,
   so a forgotten `admin.php` is inert rather than a public CMS.
2. **Dev marker.** Also require `data/.dev` to exist. `deploy.sh` never copies it.
3. **CSRF token** on every admin POST, session-bound.
4. **Upload validation** — extension *and* MIME allowlist (`jpg png webp gif svg pdf`),
   size cap, randomised stored filenames, images re-encoded through GD to strip
   anything embedded. Plus the `.htaccess` in both media dirs:

   ```apache
   <FilesMatch "\.(php|phar|phtml|php[0-9]?)$">
     Require all denied
   </FilesMatch>
   ```

   Denying the files outright rather than the older `php_flag engine off`, which only
   exists under mod_php and throws a 500 on the PHP-FPM setups most hosts now run.
5. **`data/` denied** over HTTP, as above. Verify by requesting
   `localhost:8000/data/portfolio.db` and confirming 403.
6. **Escape on output.** `e()` = `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`, used on
   every value. You are the only author, but a cert title with an `&` shouldn't break
   the page.

Layers 1 and 2 mean the delete step is a *belt*, not the only thing standing between
your site and a stranger's edit button.

---

## 8. Deploying

`deploy.sh` makes the delete automatic instead of something you have to remember:

```bash
#!/usr/bin/env bash
set -euo pipefail
rm -rf dist && mkdir -p dist
rsync -a --delete \
  --exclude 'admin.php'  --exclude 'deploy.sh'  --exclude 'dist' \
  --exclude 'migrations' --exclude 'PLAN.md'    --exclude 'README.md' \
  --exclude '.git'       --exclude 'data/.dev' \
  --exclude '*.db-wal'   --exclude '*.db-shm' \
  ./ dist/
[ -e dist/admin.php ] && { echo "FAIL: admin.php leaked into dist"; exit 1; }
[ -e dist/data/.dev ] && { echo "FAIL: dev marker leaked into dist"; exit 1; }
echo "dist/ ready — upload its contents to your host."
```

The check is "`dist/admin.php` does not exist", **not** "the string `admin.php` is
absent from `dist/`" — the guarded `require` line in `index.php` still mentions the
filename after deploy, and always will. That reference is inert precisely because
`is_file()` returns false. A string grep here would fail on every single run.

`data/portfolio.db` **is** copied — that is the whole point, it carries your content.
Sync is one-way, local → live. You never edit live; you edit locally and re-deploy.
Run `sqlite3 data/portfolio.db 'PRAGMA wal_checkpoint(TRUNCATE);'` before deploying so
the `.db` file is self-contained.

---

## 9. Phases

| # | Phase | Delivers |
|---|-------|----------|
| 0 | Environment | `brew install php@8.4`, verify `pdo_sqlite`, `php -S localhost:8000` serves a page |
| 1 | Skeleton + DB | Layout, `config.php`, `lib/db.php`, migration runner, `001_init.sql`, seed data |
| 2 | Public site | All sections render from the DB. Responsive, dark/light. Zero admin code exists yet |
| 3 | Admin core | The guarded `require`, toolbar, CSRF, edit-mode toggle, inline `settings` editing |
| 4 | Collections | Add/edit/delete/reorder/hide for projects, certificates, skills, experience |
| 5 | Media | Uploader, `pictures/` + `certificates/` routing, picker, alt text, PDF thumbnails |
| 6 | Theme + sections | Colour/font panel, section reorder and enable toggles |
| 7 | Harden + ship | Security items in §7, `deploy.sh`, backup button, README |
| 8 | Optional | Contact form → `messages` table; `revisions` table for undo; per-project detail pages |

Phase 2 finishing before Phase 3 starts is deliberate: it proves the site stands up
with no admin code in existence, which is exactly the state it ships in.

---

## 10. Acceptance test

The one test that matters, run at the end of every phase from 3 onward:

```bash
php -S localhost:8000 &
open http://localhost:8000                    # buttons visible, edit something
mv admin.php /tmp/admin.php.bak
open http://localhost:8000                    # ← buttons GONE, edit still there
grep -rn 'ADMIN_FILE' --include='*.php' . | grep -v '^./admin.php'
# must return exactly TWO functional lines: the define in config.php and the
# guarded require in index.php. Both are inert with the file deleted.
mv /tmp/admin.php.bak admin.php
```

Plus: browser console clean with the file removed, `curl -I localhost:8000/data/portfolio.db`
returns 403, and `curl -X POST localhost:8000/admin.php` returns 404 once deleted.

---

## 11. Open questions

1. **Sections.** Assumed: hero, about, skills, projects, certificates, experience,
   contact. Say the word if you want a blog, testimonials, or a downloadable CV.
2. **Certificates on hand.** How many, and are they PDFs or images? Affects whether
   Phase 5 needs PDF thumbnailing (Imagick) or can stay on GD alone.
3. **Host.** Any PHP shared host works. If you already have one, its PHP version and
   whether it honours `.htaccess` (Apache) or needs nginx rules changes §7 slightly.
4. **Contact form.** Needs SMTP creds or a service. Deferred to Phase 8; a `mailto:`
   link needs nothing and works day one.

---

## Appendix — if you'd rather stay on Node

Node 26 is already installed, so this skips Phase 0. Everything above ports directly:

* `express` + `better-sqlite3` (synchronous, ideal here) + `ejs` templates.
* The mechanic becomes, in `app.js`:
  ```js
  if (fs.existsSync('./admin.js')) require('./admin.js')(app, db);
  ```
  Same guarantee: no file, no routes registered, no toolbar injected.
* Schema, data attributes, allowlist, and `deploy.sh` are unchanged.

**The real trade-off is hosting, not code.** PHP uploads to a $3/mo shared host and
runs. Node needs a process manager or a platform (Railway, Render, Fly, a VPS), and
SQLite on those platforms needs a persistent volume or the DB resets on redeploy.
That is the reason for the PHP recommendation — not the language.
