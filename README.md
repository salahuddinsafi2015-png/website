# Lumen CMS — Laravel 13 + Next.js 16 + MySQL

A WordPress-style CMS: an API-first Laravel backend, a Next.js frontend for the
public site, and a full editorial dashboard for posts, pages, media, comments,
taxonomy, users and settings.

> **Status:** Phase 1 (foundation) is implemented and verified — 46 API tests and
> a production frontend build pass. See [Roadmap](#roadmap) for the remaining
> phases from the master specification.

## Stack

| Layer | Choice | Version (verified) |
| --- | --- | --- |
| API | Laravel | 13.39.0 (PHP `^8.3`) |
| Auth | Laravel Sanctum (bearer tokens) | ^4.3 |
| Authorization | `spatie/laravel-permission` | ^8.3 |
| HTML sanitising | `mews/purifier` (HTMLPurifier) | ^3.4 |
| Database | MySQL 8 (production), SQLite in-memory for tests | — |
| Frontend | Next.js App Router, JavaScript only | 16.4.3 |
| UI | Tailwind CSS v4 | ^4 |

## Repository layout

```
api/                     Laravel 13 API
  app/Enums              PostStatus, PostType, TermTaxonomy, CommentStatus, UserStatus
  app/Http/Controllers   V1\Site (public), V1\Admin, AuthController
  app/Http/Requests      Validation for every write path
  app/Http/Resources     API representation layer
  app/Models             Post, Term, Media, Comment, Setting, PostRevision, User
  app/Policies           Capability-based authorization
  app/Services           PostService, MediaService, ContentSanitizer
  database/migrations    MySQL schema
  database/seeders       Roles/permissions, settings, admin user, demo content
  tests/Feature          Auth, public content, editorial workflow, comments, media, settings
web/                     Next.js 16 frontend (JavaScript)
  app/                   Public site + admin dashboard (route group)
  components/            UI and client forms
  lib/                   API client, cached content fetchers, session, server actions
docs/ARCHITECTURE.md     Data model, auth flow, caching, security decisions
scripts/dev-setup.sh     Reproducible toolchain + dependency setup
```

## Setup

### 1. Prerequisites

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `intl`, `zip`, `gd` (or `imagick`)
- Composer 2
- Node.js 20+
- MySQL 8.0+

`bash scripts/dev-setup.sh` bootstraps the toolchain and installs dependencies.

### 2. Database

```sql
CREATE DATABASE lumen_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lumen'@'localhost' IDENTIFIED BY 'your-password';
GRANT ALL PRIVILEGES ON lumen_cms.* TO 'lumen'@'localhost';
```

### 3. API

```bash
cd api
cp .env.example .env          # set DB_*, CMS_ADMIN_*, FRONTEND_URL
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link      # serves uploaded media
php artisan serve --host=0.0.0.0 --port=8000
```

Seeding creates the roles, default settings, demo content (non-production only)
and an administrator from `CMS_ADMIN_EMAIL` / `CMS_ADMIN_PASSWORD`. **Change that
password immediately outside local development.**

### 4. Frontend

```bash
cd web
cp .env.example .env.local    # API_URL, NEXT_PUBLIC_SITE_URL
npm install
npm run dev                   # http://localhost:3000
```

The dashboard lives at `/admin`; sign in with the seeded administrator.

## Verification

```bash
cd api && php artisan test    # 93 tests, 366 assertions
cd web && npm run build       # production build
cd web && npm run lint
```

The suite covers the editorial permission matrix, sanitisation, revisions,
comment moderation and threading, media validation, settings, menu link
resolution and location uniqueness, widget rendering rules, theme switching
permissions, the block pipeline (block escaping, image URL restrictions,
unknown block rejection and revision snapshots), backups (creation, integrity
checks, database/media/full restore with the automatic safety snapshot,
downloads, permissions) and insights (unconfigured state, GA4 and AdSense
parsing against faked Google responses, key errors, permissions, and that
credentials never leak through public settings).

## API surface

Public (`/api/v1`):

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/settings` | Public site settings only |
| GET | `/posts` | Paginated published posts (`type`, `category`, `tag`, `search`, `author`, `page`) |
| GET | `/posts/{slug}` | Single post (`?password=` for protected posts) |
| GET | `/posts/{slug}/comments` | Approved comment tree |
| POST | `/posts/{slug}/comments` | Guest comment (moderated, throttled) |
| GET | `/terms` | Categories or tags (`?taxonomy=`) |
| GET | `/terms/{taxonomy}/{slug}` | Single term |

Authentication: `POST /auth/login`, `POST /auth/register` (disabled by default),
`GET /auth/me`, `PATCH /auth/profile`, `PUT /auth/password`, `POST /auth/logout`,
`POST /auth/logout-all`.

Admin (`/api/v1/admin`, bearer token + policies): dashboard statistics, posts
(CRUD, trash/restore/force-delete, revisions), media (upload/update/delete),
comments (moderate/delete), terms (CRUD, recount), settings (bulk upsert), users
(list/create/update/delete), backups (`GET/POST /backups`, `POST /backups/{id}/restore`,
`GET /backups/{id}/download`, `DELETE /backups/{id}`), and insights
(`GET /insights/analytics`, `GET /insights/adsense`, both with `?days=`).

## Navigation, widgets and appearance

- **Block editor** (Beta): posts can be written as a structured block tree
  (paragraph, heading, image, quote, list, code, divider, rich HTML) that is
  validated and rendered server-side into sanitised HTML at save time; the
  public site keeps rendering the existing `content` field only. Every save
  snapshots the block tree into the post's revisions. Classic HTML editing
  still works, and switching between the two clears the block tree safely.
- **Revision restore**: every content-changing write first snapshots the
  pre-edit post (title, excerpt, content, blocks, meta) into `post_revisions`;
  the edit page lists recent snapshots and can roll a post back. Restoring
  snapshots the current state first, so a restore is itself undoable, and it
  moves content only — status, slug, taxonomies and timestamps stay put, so
  editors without publish permission can't re-publish a post from history.
- **Menus** are assigned to theme locations (`primary`, `footer`). Items can be
  custom links, posts, pages or categories; content links are stored
  polymorphically, so they follow a slug change. Per-item label, order, nesting,
  target and visibility are editable from `/admin/menus`, and both menus and
  widgets support drag-and-drop reordering — with keyboard sorting (Space or
  Enter to lift, arrows to move, Space or Enter to drop, Escape to cancel),
  labelled screen-reader announcements and visible on-page instructions —
  both structures re-save instantly on drop.
- **Widgets** live in areas (`sidebar`, `footer`) with four renderers: rich text,
  recent posts, categories and menu. Rich text is sanitised on save. Managed at
  `/admin/widgets`.
- **Themes** are a registry in `config/cms.php` (`default`, `editorial`,
  `midnight`) plus an accent colour, stored as settings so switching takes effect
  on the next request without a deploy. Managed at `/admin/appearance`
  (administrators only).

The public site reads the menu for `primary`, the widget areas, and the active
theme from the API; changes revalidate the relevant cache tags.

## Backups

`/admin/backups` snapshots the site at three levels: **database**, **media
library** or **full site** (both in one archive). Snapshots live on the private
`local` disk, never in the public web root, and carry a SHA-256 checksum.

- Database snapshots are newline-delimited JSON produced by a portable dumper,
  so they restore identically on SQLite, MySQL and PostgreSQL without external
  binaries. Tables are ordered parents-first; transient tables (cache, sessions,
  queues) and the backups table itself are skipped.
- Restores accept data only — the schema stays owned by migrations. Every
  restore first creates an automatic safety snapshot of the current state, so
  a restore is itself undoable from the same list, and each restore refuses a
  file whose checksum no longer matches.
- Downloads stream through `/api/backups/{id}/download` using the httpOnly
  session cookie, so the API token never reaches browser JavaScript.
- Only administrators hold `backup.manage`; the newest 20 backups are kept and
  older files are pruned automatically.

## Insights: Google Analytics and AdSense

`/admin/insights` and two dashboard cards report live GA4 traffic (sessions,
users, page views, average session, daily series, top pages and acquisition
channels) and AdSense earnings (earnings, page RPM, clicks, CTR and daily
series). Numbers come straight from Google with the site's own service
account; nothing is sampled or mocked.

- Configure everything in **Settings → Integrations**: GA4 measurement ID
  (public, loads gtag.js on the front end), GA4 property ID, AdSense client ID
  (public, loads the AdSense script), AdSense account and a service account
  JSON key. The key and identifiers are stored privately and never exposed to
  visitors; the public settings endpoint only returns the measurement/client IDs.
- The API signs a JWT with the service account key (RS256 via OpenSSL), swaps
  it for an OAuth token, caches that token for 50 minutes, and calls the GA4
  Data API and AdSense Management API. Reports are cached five minutes.
- Admin pages never load gtag or AdSense; public pages inject them only when
  the IDs are present and skip `/admin` and `/api` at runtime.
- Without credentials both screens show a setup card instead of an error, and
  API failures surface the Google error message in place of the numbers.

## Capabilities

| Role | Can do |
| --- | --- |
| Administrator | Everything, including settings, backups and user management |
| Editor | Edit/delete any post, publish, moderate comments, manage taxonomy, menus, widgets and view insights |
| Author | Create, edit, delete and publish their own posts |
| Contributor | Create and edit drafts; cannot publish |

## Production deployment

Two services deploy independently: `api/` on a PHP 8.3+ host (Nginx + PHP-FPM,
MySQL, optional Redis) and `web/` as `next start` behind Nginx. Scheduled
publishing and backups run through the Laravel scheduler; health probes are
`GET /api/v1/health` and `GET /api/health`.

**Full guide: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** — server requirements,
PHP-FPM/OPcache tuning, Nginx config with TLS/caching/headers, cron, backups,
SEO and performance checklists, rollback and pre-launch smoke test.

**WordPress parity: [docs/WORDPRESS-PARITY.md](docs/WORDPRESS-PARITY.md)** —
feature-by-feature matrix of what is implemented, what differs, and what is
still on the roadmap.

## SEO

Every public page emits a unique title, meta description, self-referencing
canonical, Open Graph/Twitter cards and JSON-LD (WebSite + Organization
site-wide, BlogPosting + BreadcrumbList per post). `/sitemap.xml` lists all
posts, pages and taxonomies with `lastmod` (password-protected posts are
excluded); `/robots.txt` disallows `/admin`, `/api/` and search/password
query strings. Admin pages send `X-Robots-Tag: noindex, nofollow`.

Audit locally: `node scripts/seo-audit.mjs http://localhost:3000`.

## Tests

The suite covers the editorial permission matrix, sanitisation, revisions,
comment moderation and threading, media validation, settings, menu link
resolution and location uniqueness, widget rendering rules, theme switching
permissions, the block pipeline (block escaping, image URL restrictions,
unknown block rejection and revision snapshots), backups (creation, integrity
checks, database/media/full restore with the automatic safety snapshot,
downloads, permissions) and insights (unconfigured state, GA4 and AdSense
parsing against faked Google responses, key errors, permissions, and that
credentials never leak through public settings).

## Known constraints

- Tests run on in-memory SQLite while production targets MySQL; verify schema
  changes against MySQL before deploying.
- Media uses the local `public` disk (S3/R2 requires configuration).
- Search uses `LIKE`; a MySQL `FULLTEXT` index is the next step for large sites.
- Google reporting needs a service account key with the Analytics Data API and
  AdSense Management API enabled and property/account access granted; the
  suite exercises the client against faked HTTP responses, so live calls can
  only be verified with real credentials.
- Backups are stored on the server disk; copy them off-machine for disaster
  recovery.
