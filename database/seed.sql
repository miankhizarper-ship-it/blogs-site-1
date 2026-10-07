-- =====================================================================
-- SEED DATA — run AFTER schema.sql
-- Default admin login:  admin@site.com  /  Admin@12345   (must_change_pw = 1)
-- Editor: editor@site.com / Editor@12345 | Author: author@site.com / Author@12345
-- =====================================================================
SET NAMES utf8mb4;

-- ------------------------------------------------ users
INSERT INTO users (id, name, email, password, role, bio, slug, must_change_pw) VALUES
(1, 'Site Admin', 'admin@site.com',   '$2y$10$eAaYq7dJx5GxH0RkZ3Q9W.6oP1rB8sT2uV4wX6yZ8aB0cD2eF4gH.', 'admin',  'Founder and chief editor of the blog.', 'site-admin', 1),
(2, 'Emma Editor','editor@site.com',  '$2y$10$kK9mZp3Lx2Qj8WvN5tR7Y.qWe4rT6yU8iO0pA2sD4fG6hJ8kL0mN.', 'editor', 'Editor in charge of quality and SEO.',    'emma-editor', 1),
(3, 'Alex Writer','author@site.com',  '$2y$10$pP1oZx4Cv9Bn7MqL2wT6Y.eRt3yU5iO7pA9sD2fG4hJ6kL8zM0nQ.', 'author', 'Tech writer passionate about PHP & JS.','alex-writer', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- NOTE: bcrypt hashes above are placeholders for display; the installer script
-- (database/install.php) regenerates them with password_hash() so real logins work.

-- ------------------------------------------------ categories
INSERT INTO categories (id, name, slug, description, sort_order) VALUES
(1, 'Web Development', 'web-development', 'Tutorials and guides on modern web development.', 1),
(2, 'PHP',             'php',             'Everything PHP: from basics to advanced patterns.', 2),
(3, 'JavaScript',      'javascript',      'Vanilla JS, ES6+, Node and the browser platform.',  3),
(4, 'SEO & Marketing', 'seo-marketing',   'Grow your audience with search and content strategy.', 4),
(5, 'Design & UX',     'design-ux',       'Interfaces, typography, color and accessibility.',  5),
(6, 'Security',        'security',        'Protect your sites, apps and data.',                6)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ------------------------------------------------ tags
INSERT INTO tags (id, name, slug) VALUES
(1,'PHP 8','php-8'),(2,'MySQL','mysql'),(3,'PDO','pdo'),(4,'Security','security'),
(5,'CSS','css'),(6,'Flexbox','flexbox'),(7,'JavaScript','javascript'),(8,'ES6','es6'),
(9,'SEO','seo'),(10,'Performance','performance'),(11,'Accessibility','accessibility'),
(12,'Dark Mode','dark-mode'),(13,'Apache','apache'),(14,'Beginners','beginners')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ------------------------------------------------ blogs (10 demo posts)
INSERT INTO blogs
(id,title,slug,excerpt,content,status,is_featured,featured_order,rank,category_id,author_id,views,likes,reading_time,published_at,meta_title,meta_description,focus_keyword,robots_meta) VALUES
(1,'Build a Modern Blog from Scratch with Pure PHP and MySQL','build-modern-blog-php-mysql',
 'A complete walkthrough of building a secure, SEO-friendly blogging platform without any framework.',
 '<p>Frameworks are great, but understanding the fundamentals makes you a better engineer.</p><h2 id="planning">Planning the architecture</h2><p>We use a lightweight MVC structure: a front controller, a router, models that talk to PDO, and plain PHP views.</p><h2 id="database">Designing the database</h2><p>Normalized tables with InnoDB, utf8mb4 and foreign keys keep your data safe.</p><h2 id="security">Hardening security</h2><p>Prepared statements, CSRF tokens, hashed passwords and hardened sessions are non-negotiable.</p><p>By the end you will have a production-ready platform.</p>',
 'published',1,1,2,2,1,1520,88,7,'2026-08-12 09:00:00','Build a Blog with Pure PHP & MySQL','Learn to build a complete, secure blogging platform using pure PHP 8, MySQL and vanilla JavaScript.','php blog tutorial','index,follow'),
(2,'PDO Done Right: Prepared Statements Beyond the Basics','pdo-prepared-statements-guide',
 'Emulated prepares, binding edge cases, transactions and a tiny query layer you can copy into any project.',
 '<p>PDO is powerful, but most tutorials stop at <code>prepare()</code>.</p><h2 id="emulation">Why disable emulated prepares</h2><p>Real server-side prepares prevent entire classes of injection bugs.</p><h2 id="transactions">Safe transactions</h2><p>Wrap multi-step writes in transactions with automatic rollback.</p>',
 'published',1,2,5,2,1,980,61,6,'2026-08-25 10:30:00','PDO Prepared Statements: The Complete Guide','Master PDO prepared statements, transactions and error handling in PHP 8 with practical examples.','pdo php','index,follow'),
(3,'CSS Variables + Flexbox + Grid: A Design System That Scales','css-design-system-variables-flexbox-grid',
 'How to build a token-based CSS system with custom properties that powers both light and dark themes.',
 '<p>A design system is a set of decisions made once and reused everywhere.</p><h2 id="tokens">Tokens first</h2><p>Colors, spacing and type scales live in :root as CSS variables.</p><h2 id="layout">Layout primitives</h2><p>Flexbox for components, Grid for page layout.</p><h2 id="theming">Theming with zero extra CSS</h2><p>[data-theme=dark] overrides only the tokens.</p>',
 'published',1,3,1,5,3,2140,132,8,'2026-09-02 08:00:00','CSS Design System with Variables, Flexbox & Grid','Build a scalable, themeable CSS design system using custom properties, Flexbox and Grid.','css design system','index,follow'),
(4,'Dark Mode Done Properly: Toggle, Persist, Respect Preferences','dark-mode-toggle-javascript-localstorage',
 'The prefers-color-scheme media query, localStorage persistence and flash-free theme switching explained.',
 '<p>Dark mode is expected behavior now, not a gimmick.</p><h2 id="media-query">Respect the OS preference</h2><p>Start from prefers-color-scheme before any user choice.</p><h2 id="persist">Persist the choice</h2><p>localStorage plus an inline script avoids the white flash.</p>',
 'published',0,0,4,3,3,1310,74,5,'2026-09-14 12:00:00','Implement Dark Mode with JavaScript','A step-by-step guide to accessible, flash-free dark mode using CSS variables and localStorage.','dark mode javascript','index,follow'),
(5,'Technical SEO Checklist for Bloggers Who Ship Code','technical-seo-checklist-bloggers',
 'Clean URLs, canonicals, Open Graph, JSON-LD Article schema, sitemaps and Core Web Vitals — all in one checklist.',
 '<p>Great content nobody finds is a wasted weekend.</p><h2 id="urls">Clean URL structure</h2><p>Slug stability matters more than cleverness.</p><h2 id="structured">Structured data</h2><p>Article JSON-LD gets you rich results.</p><h2 id="cwv">Core Web Vitals</h2><p>Lazy-load images, preload fonts, cache assets.</p>',
 'published',1,4,3,4,2,1875,96,9,'2026-09-20 15:45:00','Technical SEO Checklist for Blogs','The complete technical SEO checklist: clean URLs, sitemap, RSS, JSON-LD schema and Core Web Vitals.','technical seo','index,follow'),
(6,'Stop Getting Hacked: Top 10 Web Vulnerabilities and Fixes','web-security-top-vulnerabilities-fixes',
 'SQL injection, XSS, CSRF, open redirects, insecure uploads — what they look like and exactly how to fix them.',
 '<p>Most breaches come from a handful of repeated mistakes.</p><h2 id="sqli">SQL Injection</h2><p>Prepared statements, always.</p><h2 id="xss">Cross-Site Scripting</h2><p>Escape on output, sanitize rich input with a whitelist.</p><h2 id="csrf">CSRF</h2><p>Synchronizer tokens on every state-changing form.</p>',
 'published',0,0,6,6,1,2450,158,10,'2026-09-28 09:15:00','Top 10 Web Security Vulnerabilities & Fixes','Learn the ten most common web vulnerabilities — SQLi, XSS, CSRF, upload abuse — and how to fix each one.','web security','index,follow'),
(7,'Vanilla JavaScript ES6+ Patterns You Will Use Every Day','vanilla-js-es6-patterns',
 'Destructuring, optional chaining, modules, fetch with async/await, and event delegation without jQuery.',
 '<p>The platform grew up. You rarely need a library anymore.</p><h2 id="fetch">Fetch + async/await</h2><p>Cleaner AJAX with abortable requests.</p><h2 id="delegation">Event delegation</h2><p>One listener instead of hundreds.</p>',
 'published',0,0,7,3,3,1120,67,6,'2026-10-03 11:00:00','Everyday ES6+ JavaScript Patterns','Practical modern vanilla JavaScript patterns: modules, fetch, async/await, destructuring and delegation.','vanilla javascript','index,follow'),
(8,'Apache .htaccess Masterclass: Clean URLs, Headers, Caching','apache-htaccess-masterclass',
 'Front-controller rewrites, security headers, gzip compression and browser caching rules worth copying.',
 '<p>Three blocks every site needs: rewrite, protect, accelerate.</p><h2 id="rewrite">Rewrite everything to index.php</h2><p>RewriteCond -f/-d checks keep static files fast.</p><h2 id="headers">Security headers</h2><p>X-Frame-Options, CSP, HSTS, nosniff.</p>',
 'published',0,0,8,1,1,760,41,5,'2026-10-05 16:20:00','Apache .htaccess: URLs, Headers & Caching','A practical .htaccess masterclass covering clean URLs, security headers, gzip and cache control.','htaccess apache','index,follow'),
(9,'MySQL Indexing Crash Course for Application Developers','mysql-indexing-crash-course',
 'Composite index order, covering indexes, EXPLAIN output and the three indexes a blog table actually needs.',
 '<p>Slow queries are usually missing or misordered indexes.</p><h2 id="composite">Leftmost prefix rule</h2><p>(status, published_at) helps status filters but not the reverse.</p><h2 id="explain">Reading EXPLAIN</h2><p>Type ALL means full scan; ref/range mean you win.</p>',
 'published',0,0,9,2,2,690,38,7,'2026-10-06 07:40:00','MySQL Indexing Crash Course','Understand composite indexes, covering indexes and EXPLAIN so your blog queries stay fast under load.','mysql indexes','index,follow'),
(10,'Accessible Comment Forms: Validation, ARIA and Anti-Spam','accessible-comment-form-aria-honeypot',
 'Build a comment form everyone can use: labels, live regions, keyboard flows — plus honeypots and rate limiting.',
 '<p>Comments are community; community must be included.</p><h2 id="aria">ARIA done right</h2><p>aria-live for validation messages, proper label associations.</p><h2 id="spam">Anti-spam without CAPTCHA pain</h2><p>Honeypot fields + per-IP rate limits stop most bots silently.</p>',
 'draft',0,0,0,5,3,0,0,5,NULL,'Accessible Comment Forms Guide','How to build an accessible, spam-resistant comment form with ARIA, honeypots and rate limiting.','accessible forms','index,follow')
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- ------------------------------------------------ blog_tags
INSERT INTO blog_tags (blog_id, tag_id) VALUES
(1,1),(1,2),(1,3),(1,4),(2,1),(2,3),(2,2),(3,5),(3,6),(4,5),(4,7),(4,12),
(5,9),(5,10),(6,4),(6,14),(7,7),(7,8),(8,13),(8,10),(9,2),(9,10),(10,11),(10,4)
ON DUPLICATE KEY UPDATE blog_id = VALUES(blog_id);

-- ------------------------------------------------ comments (mix of statuses)
INSERT INTO comments (id, blog_id, parent_id, user_id, author_name, author_email, content, status, is_admin_reply) VALUES
(1, 1, NULL, NULL, 'David Okafor',  'david@example.com',  'This is the clearest MVC explanation I have read. Does it handle file uploads too?', 'approved', 0),
(2, 1, 1,    1,    'Site Admin',    'admin@site.com',     'Yes! Uploads with MIME validation and renaming are covered in the admin module.', 'approved', 1),
(3, 1, NULL, NULL, 'SpamBot99',     'buy@traffic.example','CLICK HERE for cheap backlinks!!!', 'spam', 0),
(4, 3, NULL, NULL, 'Priya Sharma',  'priya@example.com',  'Token-based theming changed how I write CSS. Thank you!', 'approved', 0),
(5, 5, NULL, NULL, 'Tomás Silva',   'tomas@example.com',  'Bookmarked the JSON-LD section — instant improvement in rich results.', 'pending', 0),
(6, 6, NULL, NULL, 'Lena Fischer',  'lena@example.com',   'The CSRF examples saved our staging site this week.', 'approved', 0)
ON DUPLICATE KEY UPDATE content = VALUES(content);

-- ------------------------------------------------ pages (legal + about)
INSERT INTO pages (id, title, slug, content, meta_title, meta_description) VALUES
(1,'About Us','about',
 '<p>Welcome to <strong>DevJournal</strong> — an independent blog about web engineering, performance and security.</p><p>Our writers are practicing developers who publish only what they have shipped in production. We cover PHP, JavaScript, databases, SEO and design systems, with a bias toward plain, dependency-free solutions.</p><h2>What you get</h2><ul><li>Hands-on tutorials with complete code</li><li>No cookie-banner hell, no intrusive ads</li><li>Corrections published openly and quickly</li></ul><p>Reach us through the contact form — we answer every genuine message.</p>',
 'About DevJournal','Who we are and why we write about plain-PHP web engineering.'),
(2,'Privacy Policy','privacy-policy',
 '<p>This policy describes how the blog collects and uses personal data.</p><h2>Data we collect</h2><ul><li><strong>Comments:</strong> name, email and comment text, stored to display your comment and notify you of replies.</li><li><strong>Newsletter:</strong> email address only, until you unsubscribe.</li><li><strong>Contact form:</strong> name, email and message, used solely to reply to you.</li><li><strong>Analytics:</strong> anonymized page-view counts stored in our own database; no cross-site tracking cookies.</li></ul><h2>Your rights</h2><p>You may request export or deletion of your data at any time via the contact form. We never sell personal data.</p><h2>Cookies</h2><p>We set one essential session cookie and honor Do Not Track.</p>',
 'Privacy Policy','How this blog handles your personal data.'),
(3,'Terms & Conditions','terms',
 '<p>By using this website you agree to these terms.</p><h2>Content licence</h2><p>Articles are for personal reading. Republishing requires written permission and attribution.</p><h2>Comments</h2><p>Keep discussions respectful. Hate speech, spam and illegal content are removed; repeat abuse leads to a posting block.</p><h2>Disclaimer</h2><p>Tutorials are provided as-is. Test code in your own environment before production use.</p>',
 'Terms & Conditions','Terms of use for this blog.'),
(4,'Disclaimer','disclaimer',
 '<p>The information on this blog is provided for general educational purposes only. While we strive for accuracy, we make no warranties about completeness or suitability for any purpose. Any reliance you place on such material is strictly at your own risk. External links are not endorsements.</p>',
 'Disclaimer','Educational disclaimer for all articles.'),
(5,'Cookie Policy','cookie-policy',
 '<p>We use the minimum cookies possible.</p><h2>Essential</h2><ul><li><strong>Session cookie</strong> — keeps admins logged in; expires with the session.</li><li><strong>Theme preference</strong> — stored in localStorage, not a cookie.</li></ul><h2>Third parties</h2><p>If analytics or ad scripts are enabled by the site owner, they may set their own cookies; those are governed by the third party''s policy.</p>',
 'Cookie Policy','Which cookies this site sets and why.')
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- ------------------------------------------------ settings
INSERT INTO settings (setting_key, setting_value) VALUES
('site_name','DevJournal'),
('site_tagline','Plain-code web engineering, published weekly.'),
('logo',''),
('favicon',''),
('posts_per_page','9'),
('comment_moderation','1'),
('email_notify_new_comment','0'),
('social_facebook','https://facebook.com/devjournal'),
('social_twitter','https://x.com/devjournal'),
('social_linkedin','https://linkedin.com/company/devjournal'),
('social_instagram',''),
('social_youtube',''),
('analytics_code',''),
('adsense_code',''),
('default_meta_description','DevJournal — tutorials on PHP, JavaScript, MySQL, SEO and web design.'),
('contact_email','hello@devjournal.example'),
('footer_about','DevJournal is an independent blog about pragmatic web engineering — PHP, JavaScript, databases, performance and SEO. New guides every week.'),
('copyright_start','2026')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ------------------------------------------------ sample subscribers + messages
INSERT INTO subscribers (email, is_active) VALUES
('reader1@example.com',1),('reader2@example.com',1),('reader3@example.com',1)
ON DUPLICATE KEY UPDATE is_active = VALUES(is_active);

INSERT INTO contact_messages (name, email, subject, message, is_read) VALUES
('Jordan Lee','jordan@example.com','Guest post idea','I would love to write about MariaDB window functions for you.',0),
('Sam Rivera','sam@example.com','Report a bug','Typo in the PDO article code sample, line 12.',1);

-- ------------------------------------------------ demo view history (for dashboard charts)
INSERT INTO blog_views (blog_id, viewed_on, ip_hash)
SELECT b.id, DATE_SUB(CURDATE(), INTERVAL FLOOR(RAND()*30) DAY), MD5(CONCAT('demo', RAND()))
FROM blogs b JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) x
WHERE b.status='published';
