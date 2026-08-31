# Portfolio

A database-driven portfolio you edit through on-page buttons. Ship it by
deleting one file: the buttons vanish, every change stays.

## Run it locally

```bash
php -S localhost:8000 -t . tools/router.php
```

Open <http://localhost:8000>. The toolbar is bottom-right. Hit **Edit**, then
click any text on the page to change it.

Use the router script rather than plain `php -S`. The built-in server ignores
`.htaccess`, so without it `localhost:8000/data/portfolio.db` would download
your whole database in dev while being correctly blocked in production.

## How it works

All content lives in `data/portfolio.db`. The public site reads it; `admin.php`
writes it. The only connection between them is the last line of `index.php`:

```php
if (is_file(ADMIN_FILE)) require ADMIN_FILE;
```

Delete `admin.php` and there is no toolbar, no admin JavaScript, and no admin
endpoint on the server — but the database, and therefore the site, is unchanged.

Two rules keep that guarantee true. Break either and the site stops being
shippable:

1. **Content never lives in `admin.php`.** It lives in SQLite.
2. **The public site never reads from `admin.php`.** Helpers live in `lib/`.

## Editing

| Where | What you can change |
|---|---|
| Click text on the page (edit mode) | Any heading, paragraph, name, or label |
| Click an image (edit mode) | Opens the media picker |
| The ✎ on a card or row | Every field of that entry, including ones the page doesn't show — URLs, tags, dates, credential IDs |
| ↑ ↓ 👁 🗑 on a card | Reorder, show/hide, delete |
| **+ Add** under a section | New project, certificate, skill, or job |
| **Content** panel | Every text field on the site, grouped, plus the theme colours and fonts |
| **Sections** panel | Turn whole sections off, or reorder them |
| **Media** panel | Upload and delete pictures and certificates |
| **Backup** | Downloads a zip of the database plus both media folders |

Changes save as you type. There is no publish step locally — the database *is*
the site.

## Adding certificates

Drop files into `certificates/`, then:

```bash
php tools/import_certificates.php            # dry run — shows what it would add
php tools/import_certificates.php --write    # import

php tools/build_certificate_thumbnails.php --write   # build the previews
```

Both skip work already done, so re-run them whenever you add files. Titles are
guessed from filenames and are worth correcting in the admin afterwards.

`build_certificate_thumbnails.php` renders page 1 of each PDF (and downscales
image certificates) into `pictures/thumbs/`, then points the certificate row at
it. `file_path` still points at the original, so the download is the real
document at full quality — only the preview is small. PDF previews need poppler
(`brew install poppler`); without it PDFs fall back to a badge and the site
still works.

You can also upload certificates one at a time through the Media panel, which is
the better route for anything that needs a specific title anyway.

**To keep a file but leave it off the site**, rename it with a leading
underscore — `_volleyball-silver-medal.jpg`. Both tools skip it and `deploy.sh`
does not upload it, so nothing is deleted and nothing is published.

## Sections

`hero · about · skills · experience · projects · publications · certificates ·
education · leadership · contact`

Reorder or switch any of them off in the **Sections** panel. Each maps to
`partials/<key>.php` and a row in the `sections` table.

## Deploying

```bash
./deploy.sh
```

This builds `dist/` — the exact files to upload — with `admin.php`, `tools/`,
`migrations/` and the dev marker excluded, then verifies the result. Upload the
*contents* of `dist/` to your web root over SFTP.

Deployment is one-way: local → live. You edit locally and re-deploy. Since
`data/portfolio.db` is copied up with your content in it, there is nothing to
run on the host and no database to configure.

After uploading, confirm:

- the site loads with your content, and shows no toolbar
- `yoursite.com/data/portfolio.db` returns **403**
- `yoursite.com/admin.php` returns **404**

### index.html and hosts like InfinityFree

`index.html` **cannot** include `index.php`. A `.html` file is served as static
text — the server never executes it — so PHP written inside one is delivered to
the browser as visible source rather than run. There is no version of that idea
that works.

What actually causes the problem: free hosts drop a placeholder `index.html`
into `htdocs`, and if the server prefers `.html` over `.php` that placeholder is
served instead of the site. Two things in the build fix it:

- `.htaccess` sets `DirectoryIndex index.php index.html`, so `index.php` wins
  at `/` and the URL stays clean.
- `index.html` is shipped anyway, as a fallback that redirects to `index.php`.
  Uploading it overwrites the host's placeholder, so even a server that ignores
  `DirectoryIndex` lands on the real site.

If that page is ever visible for more than an instant, PHP is not running on the
host — check it is enabled for the domain.

Any PHP 8 host with `pdo_sqlite` works. Make sure `certificates/`, `pictures/`
and `data/` are writable only if you intend to run the admin there — you don't.

## Security

The admin is dev-only, and two independent conditions disable it, either of
which is false on a live host:

1. `data/.dev` must exist — `deploy.sh` never copies it.
2. The request must come from `127.0.0.1` or `::1`.

Deleting the file is the third layer, not the only one. On top of that: writes
carry a token in a custom header that a cross-origin page cannot set; every
write is checked against a hardcoded table→column allowlist in `admin.php`;
uploads are validated by real MIME type, stored under randomised names, and
re-encoded through GD, which strips metadata and anything appended to the file.
SVG uploads are refused because an SVG can carry script and would be served
from your own origin.

## Layout

```
index.php        the public page
index.html       static fallback that redirects to index.php (see above)
.htaccess        DirectoryIndex + hardening for the live host
admin.php        ← DELETE TO SHIP. Toolbar, panels, endpoints.
config.php       paths and boot
lib/             db connection, content readers, render helpers
partials/        one file per section
assets/          css and public js
pictures/        images, plus thumbs/ for generated certificate previews
certificates/    certificate files
files/           the CV download
data/            portfolio.db  (never served over HTTP)
migrations/      schema, applied automatically on boot
tools/           dev-only scripts
deploy.sh        builds dist/
```

To add a new section: create `partials/yours.php`, then add a row to the
`sections` table. To make anything editable, give the element
`data-edit="setting:your.key"` — the admin finds it on its own.
