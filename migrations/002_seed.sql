-- Seed content. Every row here is editable from admin.php — this is starting
-- material, not fixed copy. `type` and `label` are what let the admin panel
-- build the correct input for each field without a hand-written form.

INSERT OR IGNORE INTO sections (key, title, enabled, sort) VALUES
    ('hero',         'Home',         1, 10),
    ('about',        'About',        1, 20),
    ('skills',       'Skills',       1, 30),
    ('projects',     'Projects',     1, 40),
    ('certificates', 'Certificates', 1, 50),
    ('experience',   'Experience',   1, 60),
    ('contact',      'Contact',      1, 70);

INSERT OR IGNORE INTO settings (key, value, type, label, section, sort) VALUES
    -- Site
    ('site.title',        'Ahmed',                          'text',     'Your name',          'Site', 10),
    ('site.tagline',      'Developer',                      'text',     'Tagline',            'Site', 20),
    ('seo.description',   'Portfolio, projects and certifications.', 'textarea', 'Meta description', 'Site', 30),

    -- Hero
    ('hero.eyebrow',      'Hello, I am',                    'text',     'Eyebrow line',       'Hero', 10),
    ('hero.title',        'Ahmed',                          'text',     'Headline',           'Hero', 20),
    ('hero.subtitle',     'I build things for the web — clean code, useful tools, and the occasional experiment.', 'textarea', 'Subheadline', 'Hero', 30),
    ('hero.cta_label',    'View my work',                   'text',     'Primary button',     'Hero', 40),
    ('hero.cta_url',      '#projects',                      'url',      'Primary button link','Hero', 50),
    ('hero.cta2_label',   'Get in touch',                   'text',     'Secondary button',   'Hero', 60),
    ('hero.cta2_url',     '#contact',                       'url',      'Secondary link',     'Hero', 70),
    ('hero.image',        '',                               'image',    'Portrait',           'Hero', 80),

    -- About
    ('about.heading',     'About',                          'text',     'Heading',            'About', 10),
    ('about.body',        'Write a couple of paragraphs here about what you do, what you care about, and what you are looking for.

Leave a blank line between paragraphs and they will render separately.', 'textarea', 'Body copy', 'About', 20),
    ('about.image',       '',                               'image',    'Photo',              'About', 30),

    -- Skills
    ('skills.heading',    'Skills',                         'text',     'Heading',            'Skills', 10),
    ('skills.intro',      'Tools and technologies I work with.', 'textarea', 'Intro',          'Skills', 20),

    -- Projects
    ('projects.heading',  'Projects',                       'text',     'Heading',            'Projects', 10),
    ('projects.intro',    'A selection of things I have built.', 'textarea', 'Intro',          'Projects', 20),

    -- Certificates
    ('certificates.heading', 'Certificates',                'text',     'Heading',            'Certificates', 10),
    ('certificates.intro',   'Courses and certifications I have completed.', 'textarea', 'Intro', 'Certificates', 20),

    -- Experience
    ('experience.heading','Experience',                     'text',     'Heading',            'Experience', 10),
    ('experience.intro',  '',                               'textarea', 'Intro',              'Experience', 20),

    -- Contact
    ('contact.heading',   'Get in touch',                   'text',     'Heading',            'Contact', 10),
    ('contact.body',      'The quickest way to reach me is by email.', 'textarea', 'Body copy','Contact', 20),
    ('contact.email',     'ahmedrafatk@gmail.com',          'email',    'Email address',      'Contact', 30),
    ('contact.github',    '',                               'url',      'GitHub URL',         'Contact', 40),
    ('contact.linkedin',  '',                               'url',      'LinkedIn URL',       'Contact', 50),
    ('contact.x',         '',                               'url',      'X / Twitter URL',    'Contact', 60),
    ('contact.cv_url',    '',                               'url',      'CV download link',   'Contact', 70),

    -- Footer
    ('footer.text',       'Built from scratch.',            'text',     'Footer line',        'Footer', 10),

    -- Theme (every theme.* key becomes a CSS custom property, except default_mode)
    ('theme.accent',      '#6366f1',                        'color',    'Accent colour',      'Theme', 10),
    ('theme.accent_2',    '#22d3ee',                        'color',    'Secondary accent',   'Theme', 20),
    ('theme.radius',      '14px',                           'text',     'Corner radius',      'Theme', 30),
    ('theme.max_width',   '1100px',                         'text',     'Content width',      'Theme', 40),
    ('theme.font_body',   'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', 'text', 'Body font', 'Theme', 50),
    ('theme.font_heading','system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', 'text', 'Heading font', 'Theme', 60),
    ('theme.default_mode','dark',                           'select',   'Default mode',       'Theme', 70);

-- Sample rows so the site is not empty on first run. Delete them from the
-- admin once you have your own.
INSERT OR IGNORE INTO skills (id, name, category, level, sort) VALUES
    (1, 'PHP',        'Backend',  70, 10),
    (2, 'Python',     'Backend',  80, 20),
    (3, 'JavaScript', 'Frontend', 75, 30),
    (4, 'HTML & CSS', 'Frontend', 85, 40),
    (5, 'SQLite',     'Data',     70, 50),
    (6, 'Git',        'Tooling',  75, 60);

INSERT OR IGNORE INTO projects (id, title, slug, summary, description, tags, sort) VALUES
    (1, 'This portfolio', 'portfolio',
     'A database-driven portfolio with an admin panel that deletes itself at deploy time.',
     'Built on PHP and SQLite. All content lives in the database, edited through on-page buttons loaded by a single admin file. Deleting that file ships the site with every change intact and no admin code on the server.',
     'PHP, SQLite, Vanilla JS', 10),
    (2, 'Sample project', 'sample',
     'Replace this with something you have built.',
     'Use the admin panel to edit this text, add a picture, set the repository and live links, or delete the row entirely.',
     'Add, your, tags', 20);

INSERT OR IGNORE INTO experience (id, role, org, location, start_date, end_date, bullets, sort) VALUES
    (1, 'Your most recent role', 'Company name', 'City', '2024-01', '',
     'Describe an achievement, with a number attached if you have one.
Keep each bullet on its own line.
Three or four bullets per role reads best.', 10);

INSERT OR IGNORE INTO certificates (id, title, issuer, issue_date, credential_url, sort) VALUES
    (1, 'Sample certificate', 'Issuing organisation', '2025-06', '', 10);
