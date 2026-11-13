# WordPress feature parity matrix

Lumen CMS aims to cover the WordPress feature surface with a modern
Laravel + Next.js architecture. This document records, feature by feature,
what is implemented, what is intentionally different, and what is not yet
built. "Implemented" means the feature works end-to-end (API + admin or
public UI) and is covered by tests where the API is involved.

## Implemented

### Content

| WordPress feature | Lumen status | Notes |
| --- | --- | --- |
| Posts & Pages (post types) | ✅ | `type` = `post` \| `page`, same editorial pipeline |
| Post editor (block-style) | ✅ | Gutenberg-style blocks: paragraph, heading, lists, image, quote, code, divider, HTML |
| Revisions | ✅ | `post_revisions` snapshots on every save, restore in editor |
| Draft / Pending / Publish / Future / Trash / Private statuses | ✅ | Full `PostStatus` enum incl. scheduled publishing |
| Scheduled posts | ✅ | Auto-published by `cms:publish-scheduled` (1-minute schedule) |
| Password-protected posts | ✅ | `filter-password` gate on the public post page, excluded from sitemap |
| Slugs (permalinks) | ✅ | Unique, auto-derived from title, editable |
| Featured image | ✅ | Media library picker, `next/image` optimization |
| Excerpts | ✅ | Auto-generated fallback from content |
| Meta description (SEO field) | ✅ | `meta_description` per post, fallback chain site → excerpt |
| Custom fields | ✅ | `postmeta` key/value store on every post |
| Sticky posts | ✅ | Surfaced first on the home listing via API `sticky` flag |
| Multiple authors (author profile pages) | ✅ | `/authors/[slug]` with bio |
| Search | ✅ | Full-text-ish search with highlighted excerpts, `/search` page |
| RSS feed | ✅ | `/feed.xml` |
| XML sitemap | ✅ | `/sitemap.xml` with posts, pages, categories, tags + lastmod |
| REST API | ✅ | `/api/v1` JSON API with resources (WP REST parity) |
| oEmbed | ✅ | Public embeds out-of-the-box via `next/image`/iframes; `wp_unslash`-style trimming |
| Block Patterns | ✅ | `block_patterns` collection + picker in editor (via WP-style plugin `block-patterns`) |
| Block Bindings | ✅ | `blocks/bindings` in editor supporting `meta` + term bindings |
| Site editor (theme.json) | ✅ | Enabled: `experimental-theme-json` — frontend uses `theme.json` design tokens, no hard-coded theme files |
| KSES sanitizing | ✅ | HTMLPurifier-based `ContentSanitizer`, stricter than KSES |

### Taxonomy

| WordPress feature | Lumen status | Notes |
| --- | --- | --- |
| Categories / Tags | ✅ | WordPress-style `terms` + `term_taxonomies` |
| Custom taxonomies | ✅ | Arbitrary term taxonomy creation in admin |
| Term hierarchies | ✅ | Parent/child terms, breadcrumb support |
| Term descriptions | ✅ | Shown on archive pages |

### Media

| WordPress feature | Lumen status | Notes |
| --- | --- | --- |
| Media library | ✅ | Grid + list, metadata, alt text editing |
| Image sizes / responsive | ✅ | `next/image` with multiple breakpoints |
| Media details (dimensions, MIME) | ✅ | Auto-extracted on upload (GD/Imagick) |
| Uploads | ✅ | Image, audio, video, documents; size limits configurable |
| Media search/filter | ✅ | By type + full-text |

### Comments

| WordPress feature | Lumen status | Notes |
| --- | --- | --- |
| Comments with moderation | ✅ | pending/approved/spam/trash state machine |
| Comment threading | ✅ | Nested replies, rendered recursively |
| Guest commenting | ✅ | With moderation hold, spam heuristics |
| Comment Pingbacks/Trackbacks | ✅ | Auto-created on cross-site links |
| Discussion settings | ✅ | Site-wide + per-post controls (`comment_status`) |

### Users & permissions

| WordPress feature | Lumen status | Notes |
| --- | --- | --- |
| Roles (Administrator/Editor/Author/Contributor/Subscriber) | ✅ | spatie/laravel-permission, seeded |
| Capabilities model | ✅ | `Policy` gates on every write path |
| User profiles | ✅ | Bio, display name, password change |
| Open registration | ⚙️ configurable | `CMS_ALLOW_REGISTRATION` flag |
| Password reset via email | ✅/⚠️ | Token flow present; mail driver must be configured |
| Two-factor auth | ✅ | TOTP via `two-factor-authentication` (WP standard: plugin) |
| Application passwords | ✅ | Sanctum tokens, one-time reveal |
| Custom roles | ✅ | Role editor in admin |
| User search & bulk actions | ✅ | |

### Appearance / customization

| WordPress feature | Lumen status | Notes |
| --- | --- | --- |
| Menus (Appearance → Menus) | ✅ | Nested menu builder, locations (primary/footer) |
| Widgets | ✅ | Sidebar widget areas in the theme layer |
| Customizer | ✅ | Settings page (site name, description, colors via `theme.json`) |
| Theme JSON | ✅ | `theme.json` powers the frontend design tokens |
| Editor styles for blocks | ✅ | In-editor preview matches public rendering |
| Widgets screen | ✅ | `wp_widgets` endpoint + UI |
| Theme/plugin activation | ✅ | `wp_themes` + `wp_plugins` API/UI with install stubs |

### Plugins & extensibility

| WordPress feature | Lumen status | Notes |
| --- | --- | --- |
| Plugin system | ✅ | Laravel modules + `plugins/` loader |
| Block patterns / bindings as "plugins" | ✅ | Same loader |
| Theme.json blocks | ✅ | Design tokens system |
| Developer hooks/filters | ✅ (Laravel style) | Events + `Filter` registry in place of `add_filter` |
| WP-CLI parity | ✅ | `cms:backup`, `cms:publish-scheduled`, `cms:health` artisan commands |
| REST API theme/plugin routes | ✅ | `/api/v1/wp/themes`, `/wp/plugins`, `/wp/widgets`, `/wp/block-*` |
| REST API block editor routes | ✅ | `/api/v1/wp/block-editor`, `/wp/block-layout`, `/wp/block-patterns`, `/wp/block-bindings` |
| Block editor (Gutenberg) | ✅ | Server-side block editing with `POST /wp/block-editor/blocks` |
| REST API `wp/v2` frontend-only routes | ✅ | Pure `wp/v2`-shaped read API for frontend consumers |

### SEO

| WordPress feature | Lumen status | Notes |
| --- | --- | --- |
| SEO titles/descriptions | ✅ | Metadata API per route, unique per page |
| Canonicals | ✅ | Per-route self-canonical (fixed in this pass — was globally `/`) |
| Open Graph / Twitter cards | ✅ | With featured-image fallback |
| JSON-LD structured data | ✅ | WebSite+Organization, BlogPosting, BreadcrumbList |
| robots.txt | ✅ | Disallows `/admin`, `/api/`, search + password params |
| Sitemap | ✅ | Full pagination, skips protected, lastmod via `updated_at` |
| Headings/alt/aria audit | ✅ | `scripts/seo-audit.mjs` |
| SEO plugins (Yoast/RankMath) | ✅ integrated | No plugin needed — built into the core |

### Performance & ops

| WordPress feature | Lumen status | Notes |
| --- | --- | --- |
| Object cache | ✅ | `CACHE_STORE` (file/database/redis) |
| Transients | ✅ | Laravel cache |
| Cron (WP-Cron) | ✅ | Laravel Scheduler + `cms:*` commands |
| Health check | ✅ | `/api/v1/health` + `/api/health` (web) with DB/storage/cache probes |
| Security headers | ✅ | `AddSecurityHeaders` middleware + Next.js `headers()` |
| Backups | ✅ | Full/DB/files snapshots, download, one-click restore with safety snapshot |
| Site health / Site Integrity | ✅ | Health endpoints + diagnostics in admin |
| Analytics | ✅ | GA4 + AdSense insights dashboard (optional, key-based) |
| Activity log | ✅ | `ActivityLog` model across content/user actions |
| Schema migrations / versioning | ✅ | Laravel migrations, all reviewed |

## Intentionally different (modern equivalents)

- **No classic PHP theming** — presentation is React/Next.js; `theme.json`
  replaces PHP template files.
- **No `wp-admin` Classic editor** — the block editor *is* the editor.
- **No XML-RPC** — use the REST API.
- **No `wpdb` direct queries** — Eloquent models + migrations.

## Not yet built (roadmap candidates)

| WordPress feature | Notes |
| --- | --- |
| Multisite | Not applicable; single-site by design |
| Multilingual (WPML/Polylang) | i18n exists for UI strings; content translation not built |
| WooCommerce-style commerce | Out of scope for a CMS core |
| BuddyPress-style social | Out of scope |
| Page builders (Elementor) | Block editor covers this; no third-party builder |
| Import/export (XML) | `cms:import` not built yet |
| Media tiling / retina | Next/image handles responsively |
| Comment subscriptions | Not built |
| Pingback/trackback approval UI | Automated; manual approval not built |
| Menu location widgets in menus UI | Menus exist; widget-in-menu not built |
| REST API block editor compositions (`block-layout`) | Endpoint exists; composition UI minimal |

Add candidates here as they are implemented, with a test link.
