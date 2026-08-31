-- Initial schema.
--
-- Two shapes of content:
--   settings   key/value for every one-off field, carrying the UI metadata the
--              admin panel needs to render the right widget automatically
--   collections one table each, all sharing `sort` and `visible` so drag-reorder
--              and show/hide work without per-table special casing

CREATE TABLE IF NOT EXISTS settings (
    key     TEXT PRIMARY KEY,
    value   TEXT NOT NULL DEFAULT '',
    type    TEXT NOT NULL DEFAULT 'text',   -- text|textarea|color|image|url|email|bool|select
    label   TEXT NOT NULL,
    section TEXT NOT NULL,
    sort    INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS media (
    id         INTEGER PRIMARY KEY,
    path       TEXT NOT NULL UNIQUE,        -- web-root relative, e.g. 'pictures/me.jpg'
    kind       TEXT NOT NULL,               -- 'picture' | 'certificate'
    alt        TEXT NOT NULL DEFAULT '',
    mime       TEXT,
    bytes      INTEGER,
    width      INTEGER,
    height     INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS projects (
    id          INTEGER PRIMARY KEY,
    title       TEXT NOT NULL DEFAULT 'Untitled project',
    slug        TEXT,
    summary     TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    media_id    INTEGER REFERENCES media(id) ON DELETE SET NULL,
    repo_url    TEXT NOT NULL DEFAULT '',
    live_url    TEXT NOT NULL DEFAULT '',
    tags        TEXT NOT NULL DEFAULT '',   -- comma separated
    sort        INTEGER NOT NULL DEFAULT 0,
    visible     INTEGER NOT NULL DEFAULT 1,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS certificates (
    id             INTEGER PRIMARY KEY,
    title          TEXT NOT NULL DEFAULT 'Untitled certificate',
    issuer         TEXT NOT NULL DEFAULT '',
    issue_date     TEXT NOT NULL DEFAULT '',   -- 'YYYY-MM' or 'YYYY-MM-DD'
    expiry_date    TEXT NOT NULL DEFAULT '',   -- empty = does not expire
    credential_id  TEXT NOT NULL DEFAULT '',
    credential_url TEXT NOT NULL DEFAULT '',
    file_path      TEXT NOT NULL DEFAULT '',   -- 'certificates/aws-saa.pdf'
    media_id       INTEGER REFERENCES media(id) ON DELETE SET NULL,  -- preview image
    sort           INTEGER NOT NULL DEFAULT 0,
    visible        INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS skills (
    id       INTEGER PRIMARY KEY,
    name     TEXT NOT NULL DEFAULT 'New skill',
    category TEXT NOT NULL DEFAULT 'Other',
    level    INTEGER NOT NULL DEFAULT 0,      -- 0-100, 0 hides the bar
    icon     TEXT NOT NULL DEFAULT '',
    sort     INTEGER NOT NULL DEFAULT 0,
    visible  INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS experience (
    id         INTEGER PRIMARY KEY,
    role       TEXT NOT NULL DEFAULT 'New role',
    org        TEXT NOT NULL DEFAULT '',
    location   TEXT NOT NULL DEFAULT '',
    start_date TEXT NOT NULL DEFAULT '',
    end_date   TEXT NOT NULL DEFAULT '',      -- empty = Present
    bullets    TEXT NOT NULL DEFAULT '',      -- one per line
    url        TEXT NOT NULL DEFAULT '',
    sort       INTEGER NOT NULL DEFAULT 0,
    visible    INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS sections (
    key     TEXT PRIMARY KEY,                 -- matches partials/<key>.php
    title   TEXT NOT NULL DEFAULT '',         -- nav label
    enabled INTEGER NOT NULL DEFAULT 1,
    sort    INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_projects_sort     ON projects(sort);
CREATE INDEX IF NOT EXISTS idx_certificates_sort ON certificates(sort);
CREATE INDEX IF NOT EXISTS idx_skills_sort       ON skills(sort);
CREATE INDEX IF NOT EXISTS idx_experience_sort   ON experience(sort);
CREATE INDEX IF NOT EXISTS idx_media_kind        ON media(kind);
