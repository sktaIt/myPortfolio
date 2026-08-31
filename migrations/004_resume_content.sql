-- Content taken from the CV in files/ahmed-rafat-cv.pdf, and certificate details
-- read out of each certificate file itself rather than guessed from filenames.
--
-- This replaces the placeholder seed rows from 002. Everything below is
-- editable in the admin — it is a starting point, not a fixed page.

-- ── Clear the seed and the first-pass certificate import ────────────────────
DELETE FROM certificates;
DELETE FROM media;
DELETE FROM skills;
DELETE FROM experience;
DELETE FROM projects;

-- ── Identity and copy ───────────────────────────────────────────────────────
UPDATE settings SET value = 'Ahmed Rafat'                WHERE key = 'site.title';
UPDATE settings SET value = 'Software Engineer (AI/ML)'  WHERE key = 'site.tagline';
UPDATE settings SET value = 'Software engineer specialising in AI/ML systems, backend engineering and scalable infrastructure. Published research in applied AI and cybersecurity.'
    WHERE key = 'seo.description';

UPDATE settings SET value = 'Hello, I am'  WHERE key = 'hero.eyebrow';
UPDATE settings SET value = 'Ahmed Rafat'  WHERE key = 'hero.title';
UPDATE settings SET value = 'Software engineer building AI/ML systems and backend infrastructure — from a malware detection model at 99.5% accuracy to production pipelines that cut execution time by 80%.'
    WHERE key = 'hero.subtitle';
UPDATE settings SET value = 'View my work' WHERE key = 'hero.cta_label';
UPDATE settings SET value = '#projects'    WHERE key = 'hero.cta_url';
UPDATE settings SET value = 'Get in touch' WHERE key = 'hero.cta2_label';
UPDATE settings SET value = '#contact'     WHERE key = 'hero.cta2_url';

UPDATE settings SET value = 'I am a software engineer specialising in AI/ML systems, backend engineering and scalable infrastructure, currently at JurisTech in Malaysia.

Most of my work is about making systems faster and more reliable at production scale — multi-layer parallelisation that took average execution time from ten minutes to two, memory-efficient algorithms for large-scale data processing, and secure APIs that let separate products talk to each other without duplicating maintenance.

Alongside that I work on applied machine learning research, with published papers in AI, big data and cybersecurity. My final year project was an AI malware detection system that reached 99.5% classification accuracy and 99.6% on real-time malicious traffic detection.'
    WHERE key = 'about.body';

UPDATE settings SET value = 'The tools and technologies I work with day to day.' WHERE key = 'skills.intro';
UPDATE settings SET value = 'Research, production and side projects across AI/ML, full-stack and embedded systems.' WHERE key = 'projects.intro';
UPDATE settings SET value = 'Awards, academic recognition and professional training.' WHERE key = 'certificates.intro';
UPDATE settings SET value = '' WHERE key = 'experience.intro';

UPDATE settings SET value = 'Open to interesting problems in AI/ML and backend engineering. The quickest way to reach me is by email.'
    WHERE key = 'contact.body';
UPDATE settings SET value = 'ahmedrafatk@gmail.com'                  WHERE key = 'contact.email';
UPDATE settings SET value = 'https://github.com/sktaIt'              WHERE key = 'contact.github';
UPDATE settings SET value = 'https://www.linkedin.com/in/ahmedrafatk' WHERE key = 'contact.linkedin';
UPDATE settings SET value = 'files/ahmed-rafat-cv.pdf'               WHERE key = 'contact.cv_url';
UPDATE settings SET value = '+601135844762'                          WHERE key = 'contact.phone';
UPDATE settings SET value = 'Built from scratch — PHP, SQLite, no framework.' WHERE key = 'footer.text';

-- ── Skills ──────────────────────────────────────────────────────────────────
-- level stays 0 everywhere: the CV states no proficiency percentages and
-- inventing them would be making things up. 0 hides the bar and renders a
-- clean list instead.
INSERT INTO skills (name, category, level, sort) VALUES
    ('Python',                 'Languages', 0, 10),
    ('JavaScript',             'Languages', 0, 20),
    ('C / C++',                'Languages', 0, 30),
    ('SQL',                    'Languages', 0, 40),
    ('PHP',                    'Languages', 0, 50),

    ('TensorFlow',             'AI / ML', 0, 60),
    ('Machine Learning',       'AI / ML', 0, 70),
    ('RAG Systems',            'AI / ML', 0, 80),
    ('Pandas',                 'AI / ML', 0, 90),
    ('NumPy',                  'AI / ML', 0, 100),
    ('Data Analysis',          'AI / ML', 0, 110),
    ('Model Evaluation',       'AI / ML', 0, 120),

    ('API Development',        'Backend & Infrastructure', 0, 130),
    ('System Design',          'Backend & Infrastructure', 0, 140),
    ('Parallel Processing',    'Backend & Infrastructure', 0, 150),
    ('Algorithm Optimisation', 'Backend & Infrastructure', 0, 160),
    ('Database Automation',    'Backend & Infrastructure', 0, 170),
    ('Linux',                  'Backend & Infrastructure', 0, 180),

    ('Git',                    'Tooling', 0, 190),
    ('Docker',                 'Tooling', 0, 200),
    ('Composer',               'Tooling', 0, 210),
    ('VS Code',                'Tooling', 0, 220);

-- ── Experience ──────────────────────────────────────────────────────────────
INSERT INTO experience (role, org, location, start_date, end_date, bullets, sort) VALUES
    ('Software Engineer', 'Juris Technologies Sdn Bhd (JurisTech)', 'Malaysia', '2025-02', '',
'Engineered multi-layer parallel processing optimisations that cut average execution time from 10 minutes to 2, improving system throughput by 80% in production workflows
Designed and implemented secure internal and external APIs enabling cross-product service integration, while centralising maintenance and access control
Built memory-efficient algorithms for large-scale data processing without increasing PHP memory consumption in production
Led technical execution and deployment coordination for 5 concurrent client implementations
Developed 6 standalone production components and contributed to more than 20 system modules across backend infrastructure and business logic
Mentored and assessed incoming engineers during onboarding and technical evaluation
Advanced into technical leadership responsibilities within the first year through ownership of delivery, architecture discussions and cross-team coordination', 10),

    ('Software Engineer Intern', 'Juris Technologies Sdn Bhd (JurisTech)', 'Malaysia', '2023-10', '2024-01',
'Developed automated test coverage for multiple system components, improving validation reliability and reducing manual testing effort
Built backend utilities and reusable engineering tools that improved internal system performance and maintainability
Investigated and resolved recurring production-impacting issues affecting user experience and system stability', 20),

    ('Software Engineer Intern', 'Juris Technologies Sdn Bhd (JurisTech)', 'Malaysia', '2022-09', '2022-12',
'Implemented backend security-focused system components to strengthen platform reliability and protection mechanisms
Developed backend modules optimised for improved response time and execution efficiency
Automated repetitive database operations through custom scripting workflows', 30);

-- ── Projects ────────────────────────────────────────────────────────────────
INSERT INTO projects (title, slug, summary, description, tags, sort) VALUES
    ('AI Malware Detection', 'ai-malware-detection',
     'Final year project: an AI-powered malware detection system for PE files, reaching 99.5% classification accuracy.',
'Built an AI-powered malware detection system for PE files using machine learning classification models, with peak accuracy of 99.5% across detection experiments.

Designed a real-time TCP packet inspection and malicious traffic detection pipeline reaching 99.6% detection accuracy, covering feature extraction, model evaluation, packet analysis and system integration end to end.

The associated research was published in applied AI and cybersecurity.',
     'AI/ML, Cybersecurity, Real-Time Detection, Research', 10),

    ('AI PDF RAG Analyzer', 'ai-pdf-rag-analyzer',
     'A retrieval-augmented generation system for querying multiple PDF documents with LLM-based semantic retrieval.',
'Developed a RAG system letting users query across multiple PDF documents using LLM-based semantic retrieval.

Designed the document ingestion, chunking, retrieval and response generation workflows for contextual question answering, with a focus on scalable architecture and an extensible pipeline for document intelligence applications.',
     'LLMs, RAG, System Design', 20),

    ('SEMQ Platform', 'semq-platform',
     'A full-stack platform for exchanging academic study materials between students, built solo.',
'Built a full-stack platform for exchanging academic study materials between students.

Designed and developed the frontend, backend and database architecture independently, and managed end-to-end implementation from planning through deployment.',
     'Full-Stack, Product Development', 30),

    ('Automated IoT Fish Feeder', 'iot-fish-feeder',
     'An IoT fish feeding system with environmental monitoring and a companion mobile app.',
'Developed an IoT-based automated fish feeding system with environmental monitoring and mobile application integration.

Contributed to hardware assembly, embedded programming and backend API development, and implemented automated tracking and visualisation of water-condition metrics.',
     'IoT, Embedded Systems, APIs', 40),

    ('Mixed Reality Robotic Arm', 'mixed-reality-robotic-arm',
     'A low-cost AR/VR-assisted platform for teaching industrial robotics. Gold Award at IICE 2023.',
'Contributed to development of a low-cost AR/VR-assisted robotics teaching platform for industrial robotics education, supporting planning, implementation and software development for the mixed reality integration.

The project received the Gold Award at the IICE 2023 International Innovation Competition in Education, hosted by Universiti Teknologi Malaysia.',
     'AR/VR, Robotics, Interactive Systems', 50),

    ('This Portfolio', 'portfolio',
     'A database-driven portfolio with an admin panel that is deleted at deploy time.',
'Built on PHP and SQLite with no framework. All content lives in the database and is edited through on-page buttons loaded by a single admin file.

Deleting that one file ships the site with every change intact and no admin code on the server at all.',
     'PHP, SQLite, Vanilla JS', 60);

-- ── Publications ────────────────────────────────────────────────────────────
-- The CV lists these without links; add the URLs in the admin when you have them.
INSERT INTO publications (title, venue, year, url, summary, sort) VALUES
    ('AI malware detection and network traffic analysis', 'Applied AI & Cybersecurity', '2024', '',
     'Research associated with the final year project: machine learning classification of PE files and real-time malicious traffic detection.', 10),
    ('AI-driven credit card fraud detection', '', '', '',
     'Applied machine learning for detecting fraudulent card transactions.', 20),
    ('AI and Big Data integration', '', '', '',
     'Research on integrating artificial intelligence with big data systems.', 30);

-- ── Education ───────────────────────────────────────────────────────────────
INSERT INTO education (qualification, institution, location, year, result, notes, sort) VALUES
    ('Bachelor of Computer Engineering (Artificial Intelligence), Honours', 'UCSI University', 'Malaysia', '2024',
     'CGPA 3.73 / 4.00 — First Class Honours',
     'Scholarship awardee for the full duration of study. Seven-time Dean''s List awardee.', 10),
    ('IELTS English', '', 'Egypt', '2019', 'Band 6.0', '', 20),
    ('High School', 'Bashaer Aljazira High School', 'Saudi Arabia', '2020', 'Score 99.97%', '', 30),
    ('Qiyas Test', '', 'Saudi Arabia', '2020', 'Score 96%', '', 40);

-- ── Leadership & activities ─────────────────────────────────────────────────
INSERT INTO activities (role, org, period, bullets, sort) VALUES
    ('Organising Chairperson — Techstars Startup Weekend', 'Techstars', 'Jan 2023 – Jun 2023',
'Led coordination and execution of a 54-hour startup event hosted at UCSI University
Managed event planning, speaker coordination and the participant competition structure', 10),
    ('Event Planner — TheSpark Forum', 'TheSpark', 'Feb 2024',
'Planned the event flow and ensured the marketing was well implemented
Selected speakers with backgrounds relevant to the forum topic', 20),
    ('Volunteer — KL Startup Summit', 'KL Startup Summit', 'Jul 2024',
'Crew volunteer on the event day', 30);

-- ── Certificates ────────────────────────────────────────────────────────────
-- Titles, issuers, dates and reference numbers were read from each document.
-- media_id is filled in by tools/build_certificate_thumbnails.php.
INSERT INTO certificates (title, issuer, issue_date, credential_id, file_path, sort) VALUES
    ('Gold Award — IICE 2023 International Innovation Competition in Education',
     'Universiti Teknologi Malaysia', '2023', '',
     'certificates/2023-iice-gold-award-utm.png', 10),

    ('Dean''s List — May 2023 Semester', 'UCSI University', '2023-05', 'FETBE/DL/24/0240',
     'certificates/2023-05-deans-list-ucsi.pdf', 20),
    ('Dean''s List — January 2023 Semester', 'UCSI University', '2023-01', 'FETBE/DL/24/0239',
     'certificates/2023-01-deans-list-ucsi.pdf', 30),
    ('Dean''s List — May 2022 Semester', 'UCSI University', '2022-05', 'FETBE/DL/23/0095',
     'certificates/2022-05-deans-list-ucsi.pdf', 40),
    ('Dean''s List — January 2022 Semester', 'UCSI University', '2022-01', 'FETBE/DL/23/0094',
     'certificates/2022-01-deans-list-ucsi.pdf', 50),
    ('Dean''s List — January 2021 Semester', 'UCSI University', '2021-01', 'FETBE/DL/22/0724',
     'certificates/2021-01-deans-list-ucsi.pdf', 60),

    ('HYPERVSN Holographic Equipment Commissioning & Content Creation', 'Hertford', '2023-10', '1002059739',
     'certificates/2023-10-hertford-hypervsn-holographic.jpg', 70),
    ('The Future of Mobile App Industry', 'Huawei Developers', '2021-10', '',
     'certificates/2021-10-huawei-future-of-mobile-app-industry.jpg', 80),
    ('CCP — Create Collaborative Presentation with Canva', 'Complimentary Competency Programme', '2021-12', '',
     'certificates/2021-12-canva-collaborative-presentation.pdf', 90),
    ('Applied Digital Skills: Create a Study Schedule to Meet Your Goal', 'SOLS 24/7 Education', '2020-10', '',
     'certificates/2020-10-applied-digital-skills-sols247.pdf', 100),

    ('Volleyball — Silver Medal', '', '', '',
     'certificates/volleyball-silver-medal.jpg', 110);
