-- Three collections the resume needs that the original schema had no home for.
-- Same shape as the existing ones — `sort` and `visible` on every table — so
-- reordering, hiding and the row editor all work with no special casing.

CREATE TABLE IF NOT EXISTS education (
    id            INTEGER PRIMARY KEY,
    qualification TEXT NOT NULL DEFAULT '',
    institution   TEXT NOT NULL DEFAULT '',
    location      TEXT NOT NULL DEFAULT '',
    year          TEXT NOT NULL DEFAULT '',
    result        TEXT NOT NULL DEFAULT '',   -- CGPA, band, percentage
    notes         TEXT NOT NULL DEFAULT '',
    sort          INTEGER NOT NULL DEFAULT 0,
    visible       INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS publications (
    id      INTEGER PRIMARY KEY,
    title   TEXT NOT NULL DEFAULT '',
    venue   TEXT NOT NULL DEFAULT '',
    year    TEXT NOT NULL DEFAULT '',
    url     TEXT NOT NULL DEFAULT '',
    summary TEXT NOT NULL DEFAULT '',
    sort    INTEGER NOT NULL DEFAULT 0,
    visible INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS activities (
    id      INTEGER PRIMARY KEY,
    role    TEXT NOT NULL DEFAULT '',
    org     TEXT NOT NULL DEFAULT '',
    period  TEXT NOT NULL DEFAULT '',
    bullets TEXT NOT NULL DEFAULT '',   -- one per line
    url     TEXT NOT NULL DEFAULT '',
    sort    INTEGER NOT NULL DEFAULT 0,
    visible INTEGER NOT NULL DEFAULT 1
);

CREATE INDEX IF NOT EXISTS idx_education_sort    ON education(sort);
CREATE INDEX IF NOT EXISTS idx_publications_sort ON publications(sort);
CREATE INDEX IF NOT EXISTS idx_activities_sort   ON activities(sort);

INSERT OR IGNORE INTO sections (key, title, enabled, sort) VALUES
    ('publications', 'Publications', 1, 60),
    ('education',    'Education',    1, 80),
    ('activities',   'Leadership',   1, 90);

-- Reordered so the strongest material leads: work, then projects, then the
-- research and credentials that back them up.
UPDATE sections SET sort =  10 WHERE key = 'hero';
UPDATE sections SET sort =  20 WHERE key = 'about';
UPDATE sections SET sort =  30 WHERE key = 'skills';
UPDATE sections SET sort =  40 WHERE key = 'experience';
UPDATE sections SET sort =  50 WHERE key = 'projects';
UPDATE sections SET sort =  70 WHERE key = 'certificates';
UPDATE sections SET sort = 100 WHERE key = 'contact';

INSERT OR IGNORE INTO settings (key, value, type, label, section, sort) VALUES
    ('education.heading',    'Education',              'text',     'Heading', 'Education', 10),
    ('education.intro',      '',                       'textarea', 'Intro',   'Education', 20),
    ('publications.heading', 'Publications',           'text',     'Heading', 'Publications', 10),
    ('publications.intro',   'Published research in applied AI, big data and cybersecurity.', 'textarea', 'Intro', 'Publications', 20),
    ('activities.heading',   'Leadership & Activities','text',     'Heading', 'Leadership', 10),
    ('activities.intro',     '',                       'textarea', 'Intro',   'Leadership', 20),
    ('contact.phone',        '',                       'text',     'Phone (public if set)', 'Contact', 35);
