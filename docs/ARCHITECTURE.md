# Architecture

Lumen CMS is a headless, WordPress-style publishing platform: a Laravel 13 JSON
API (`/api`) and a Next.js 16 frontend (`/web`) that renders public content and
hosts the editorial dashboard.

```
Browser ──► Next.js (RSC, server actions, httpOnly session cookie)
                 │
                 ├─ public pages: cached content fetchers ("use cache")
                 └─ admin pages: per-request fetches with bearer token
                 │
                 ▼
            Laravel 13 API  ──►  MySQL 8
            (Sanctum tokens, policies, form requests, revisions)
```

The browser never holds an API token. Login happens in a server action, the
token is written to an httpOnly cookie, and every authenticated read/write is
performed server-side. This keeps the token out of client bundles, `localStorage`
and the network tab.

## Content model

| Table | Purpose |
| --- | --- |
| `posts` | Posts, pages and registered custom types (`type`), soft-deleted, versioned |
| `post_revisions` | Snapshot taken before each save (title, excerpt, content, status, meta) |
| `terms` | Categories and tags in one table keyed by `taxonomy`, with `parent_id` for hierarchies |
| `post_term` | Many-to-many between posts and terms |
| `media` | Uploaded files: disk, path, MIME, dimensions, alt text and caption |
| `comments` | Threaded comments with moderation status, guest author fields and request metadata |
| `menus` / `menu_items` | Named menus bound to a theme location, with polymorphic links to posts, pages or categories |
| `widgets` | Area-scoped blocks (`rich_text`, `recent_posts`, `categories`, `menu`) with ordering and visibility |
| `settings` | Typed key/value options (`string`, `integer`, `boolean`, `json`) with a public flag |
| `users` + Spatie tables | Accounts, roles and granular permissions |

### Design decisions

- **One table for posts and pages.** `type` distinguishes them and slugs are
  unique *per type*, matching WordPress: a page and a post may share a slug.
- **Content is stored as sanitised HTML.** HTMLPurifier (`mews/purifier`) runs on
  every write, so the frontend can render with `dangerouslySetInnerHTML` safely.
  Stored XSS is prevented at the boundary that owns the data.
- **Terms instead of separate tables.** Categories and tags share structure, and
  custom taxonomies can be registered in `config/cms.php` without migrations.
- **Revisions are snapshots, not diffs.** Cheap to write, trivial to restore, and
  bounded by explicit ordering when read.
- **Settings are cached by name.** `Setting::get()` memoises through the cache
  store and the model invalidates its own key on save.
- **Roles are capability-based.** Policies check granular permissions
  (`post.publish`, `post.update.own`, `comment.moderate`, …) rather than role
  names, so new roles only need a mapping in the seeder.

## Navigation, widgets and appearance

- **One menu per location.** A unique index on `menus.location` enforces it; MySQL
  allows many `NULL`s, so unassigned menus are unconstrained. Admin routes accept
  either the id or the slug.
- **Links are resolved, not copied.** Menu items store `type` plus a polymorphic
  `linkable` reference (post, page, term) and resolve their URL when serialised.
  Renaming a slug therefore updates the menu automatically; custom items keep a
  literal URL. This is why `menu_items` has `linkable_type`/`linkable_id`.
- **Widgets are declarative.** A widget stores its type, title, JSON content and
  optional menu reference; the frontend owns rendering per type. Rich text is
  sanitised at write time with the same purifier as post content, and
  `menu_id` is required only for `menu` widgets and cleared for other types.
- **Revision restore moves content, never publication state.** Restoring a
  revision writes only title, excerpt, content, blocks and meta through the
  same `prepare()` pipeline as a normal edit (blocks re-render, HTML
  re-purifies), captures the current state as a new revision first, and leaves
  status/slug/taxonomies/timestamps untouched. That keeps authors without
  publish permission from re-publishing through history, and makes every
  restore reversible from the same history list.
- **Blocks are compiled at write time.** A post's `blocks` JSON column travels
  through `BlockRenderer`, which validates each block against a per-type
  schema (type allow-list, heading levels 1–6, image URLs restricted to site
  paths and `http(s)://` so `javascript:`/`data:` payloads are rejected) and
  escapes every dynamic value into `posts.content`. The public pipeline only
  ever sees that HTML cache, so structured editing can never introduce a
  rendering path of its own. `Rich HTML` is the one unescaped block and it
  runs through `ContentSanitizer` exactly like classic content. Revisions
  snapshot the tree as well as the rendered cache, so history is
  reconstructible; an empty `blocks` payload tells the service the editor
  switched back to classic HTML and clears the stored tree.
- **Themes are code, selection is data.** Palettes are registered in
  `config/cms.php` (so a new theme is a code change, not a migration) while the
  active theme and accent live in `settings`. The layout whitelists the theme key
  and validates the accent hex before writing them to the `data-theme` attribute
  and a `--accent` custom property, so a bad value can never reach the DOM.
- **Editors manage navigation and widgets; only administrators switch themes.**
  That mirrors the expectation that menu copy and placement are editorial work
  while the visual identity is an owner decision.

## Backups

- **Portable snapshot format, not SQL dumps.** A database snapshot is
  newline-delimited JSON: a metadata line, a header line per table
  (`table`, `columns`), then one `row` object per record. It restores through
  the same query builder on SQLite, MySQL and PostgreSQL, so the CMS never
  depends on `mysqldump` being installed and the format is diffable.
- **Data only, schema by migrations.** A restore deletes and re-inserts rows;
  it never touches DDL. That keeps restored archives valid only for the
  migration state they were taken under — which is deliberate: schema changes
  belong to deployments, content belongs to backups.
- **Foreign-key-safe ordering.** Tables are dumped parents-first via
  `Schema::getForeignKeys`, deletes run in reverse, inserts in file order. The
  service also disables FK checks where the driver allows it, but the ordering
  is what makes restores correct even inside a wrapping transaction (SQLite
  ignores the pragma there), which is exactly how the tests run.
- **Restores are undoable.** Every restore writes a safety snapshot first, so
  the replaced state remains downloadable. Files are SHA-256 checked before
  restore and refused when tampered.
- **Transient tables are excluded.** Cache, sessions, queues and the backups
  table itself are skipped, so a restore cannot resurrect stale queues or
  orphan backup rows.
- **Transfers stay server-side.** The browser downloads through a Next.js route
  handler that attaches the httpOnly token, so archives never pass through
  client JavaScript and the API token is never exposed.

## Insights

- **Service-account auth without an SDK.** `GoogleApiClient` builds a JWT
  (RS256 via `openssl_sign`), exchanges it at Google's token endpoint, and
  caches the access token for 50 minutes. Both report services share it.
- **Reports are summaries, cached briefly.** GA4 is queried with four small
  `runReport` calls (totals, daily series, top pages, acquisition); AdSense
  uses one management report. Each result is cached five minutes per
  property/account and range, so a dashboard reload never burns quota.
- **Public vs private configuration.** Measurement ID and AdSense client ID
  are public settings because they ship in page source; property ID, account ID
  and the service account key are private and excluded from `/api/v1/settings`.
- **Scripts load conditionally.** `TrackingScripts` injects gtag.js and the
  AdSense loader with a small inline bootstrap that validates the IDs and skips
  `/admin` and `/api` at runtime. A client component with `usePathname` would
  have opted every public page out of static rendering, hence the inline form.
- **Failure is a state, not an exception.** Unconfigured services return
  `configured: false` with a setup reason; Google errors return
  `configured: true` plus the message, so the dashboard can render cards in
  every case.

## Authorization

| Role | Capabilities |
| --- | --- |
| Administrator | Everything, including settings, backups and user management |
| Editor | Edit/delete any post, publish, moderate comments, manage taxonomy, menus, widgets and view insights |
| Author | Create, edit, delete and publish their own posts |
| Contributor | Create drafts and edit their own drafts; cannot publish |
| Subscriber | Read-only account (no editorial capabilities) |

There is deliberately **no global `Gate::before` bypass**: administrators hold
every permission, so policy rules (such as "cannot delete your own account")
stay effective for them too.

## Rendering and caching

Next.js 16 with Cache Components enabled:

- Public fetchers in `web/lib/content.js` use `"use cache"` with `cacheLife` and
  `cacheTag` (`posts`, `terms`, `settings`, `comments`, `menus`, `widgets`,
  `post:{slug}`).
- Server actions call `revalidateTag`/`revalidatePath` after mutations, so
  publishing is visible immediately on the next request.
- Dynamic segments (`[slug]`) await `params` **inside** a Suspense boundary, so
  the route still produces a prerendered shell.
- Dashboard routes set `export const instant = false` because they read the
  session cookie on every request and must never be shared between accounts.

## SEO

- Per-post `<title>`/description with `seo_title`/`seo_description` fallbacks.
- Canonical URLs, Open Graph and Twitter cards with featured/OG image.
- `BlogPosting`/`WebPage` JSON-LD on article pages.
- `sitemap.xml` (posts, categories, tags), `robots.txt` (admin disallowed) and
  an RSS 2.0 `feed.xml`.

## Security

- Sanctum bearer tokens, revoked on logout and on password change (other
  sessions are invalidated).
- Login throttled to 5 attempts/min per email+IP; comment submission to 5/min per
  IP; admin API to 120/min.
- Uniform login failure message to prevent account enumeration; suspended
  accounts rejected.
- Media uploads restricted by MIME type **and** extension allow-list; SVG is
  refused because it can carry executable script.
- Password-protected posts withhold content until the password is supplied and
  the comparison uses `Hash::check`.
- Comment content is tag-stripped; author email, IP and user agent are hidden
  from serialisation.

## Known constraints

- Automated tests run on in-memory SQLite (fast), while production targets
  MySQL 8. Every migration is written for MySQL; schema changes must be verified
  against a real MySQL instance before deploying.
- Media is stored on the local `public` disk. Object storage (S3/R2) requires
  configuring the disk and a CDN origin for `next/image`.
- Search uses `LIKE` for portability. A MySQL `FULLTEXT` index is the next
  performance step for large archives.
