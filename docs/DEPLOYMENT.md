# Production deployment

Two services, one repository:

| Service | Stack | Typical host |
| --- | --- | --- |
| `api/` | Laravel 13 (PHP 8.3+), MySQL 8 (or PostgreSQL), Redis optional | VPS, Forge, Ploi, shared PHP host with SSH |
| `web/` | Next.js 16 (Node 22+), production build via `next start` | VPS behind Nginx, or a Node host such as Vercel |

The frontend talks to the API **server-side only**; visitors never receive the
API token or the API base URL.

> Managed static/Worker hosts (Cloudflare Pages/Workers, Netlify static) can
> serve `web/` only if `API_URL` is reachable from the Worker runtime. A
> PHP/Laravel API cannot run there — keep the API on a PHP host.

## 1. Server requirements

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `openssl`, `zip`, `gd` or `imagick` (image dimensions), `fileinfo`
- Composer 2
- MySQL 8.0+ (utf8mb4) or PostgreSQL 15+
- Node 22+ for building the frontend, Nginx (or Caddy) for TLS termination
- Optional: Redis 7 for cache/sessions/queues, Supervisor for the queue worker

## 2. Database

```sql
CREATE DATABASE lumen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lumen'@'localhost' IDENTIFIED BY '<strong-password>';
GRANT ALL PRIVILEGES ON lumen.* TO 'lumen'@'localhost';
```

## 3. API (Laravel)

```bash
cd api
cp .env.example .env
# Set APP_ENV=production, APP_DEBUG=false, APP_URL, FRONTEND_URL,
# DB_* , SESSION_SECURE_COOKIE=true, CACHE_STORE, QUEUE_CONNECTION.
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=AdminUserSeeder --force   # then change the password
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- Set `CMS_ADMIN_EMAIL` / `CMS_ADMIN_PASSWORD` before seeding and rotate the
  password after the first sign-in (Profile → Password).
- If you enable Redis: `php artisan cache:clear` once after switching stores.
- Health probe: `GET /api/v1/health` → 200 `{"status":"ok"}` (503 when the DB,
  storage or cache is unhealthy). Point your monitor at it.
- Laravel's own `/up` endpoint is also enabled for load balancers.

### PHP-FPM tuning

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0   ; restart FPM on deploy
opcache.jit=tracing
realpath_cache_size=4096K
```

`php artisan config:cache route:cache view:cache` after every deploy;
`php artisan optimize:clear` before a rollback.

### Queues and scheduled tasks (WordPress-Cron equivalents)

Scheduled post publishing and nightly backups run through the scheduler:

```cron
* * * * * cd /var/www/lumen/api && php artisan schedule:run >> /dev/null 2>&1
```

The schedule (see `api/routes/console.php`) runs:

- `cms:publish-scheduled` every minute — publishes `future` posts whose time has come
- `cms:backup full` daily at 02:30 — snapshot into `storage/app/backups`

Manual runs:

```bash
php artisan cms:publish-scheduled
php artisan cms:backup full --note="Before release 1.5"
```

If you use queues (mail, webhooks): `php artisan queue:work --tries=3 --timeout=60`
under Supervisor.

## 4. Web (Next.js)

```bash
cd web
cp .env.example .env.production   # API_URL, NEXT_PUBLIC_SITE_URL
npm ci
npm run build
NODE_ENV=production npx next start -p 3000
```

Run it under Supervisor or systemd:

```ini
[program:lumen-web]
command=/usr/bin/node node_modules/next/dist/bin/next start -p 3000
directory=/var/www/lumen/web
environment=NODE_ENV="production"
autostart=true
autorestart=true
stdout_logfile=/var/log/lumen-web.log
```

Health probe: `GET /api/health` (also verifies the API).

## 5. Nginx (TLS, compression, caching, headers)

```nginx
# API
server {
    listen 443 ssl http2;
    server_name api.example.com;
    root /var/www/lumen/api/public;
    index index.php;

    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    gzip on;
    gzip_types application/json application/xml text/css text/plain;
    gzip_min_length 512;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    location ~ /\.(?!well-known).* { deny all; }
}

# Frontend
server {
    listen 443 ssl http2;
    server_name www.example.com;
    proxy_pass http://127.0.0.1:3000;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;

    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    gzip on; gzip_types text/html text/css application/javascript application/json image/svg+xml;
    brotli on; brotli_types text/html text/css application/javascript application/json image/svg+xml;

    location /_next/static/ { add_header Cache-Control "public, max-age=31536000, immutable"; }
}
```

The app also sets these headers itself (`next.config.mjs`, `AddSecurityHeaders`),
so a proxy mistake cannot silently remove them.

TLS: `certbot --nginx -d www.example.com -d api.example.com`, then enable HSTS
only after confirming HTTPS works on every hostname.

## 6. CORS and cookies

- `FRONTEND_URL` in the API `.env` must be the exact public origin of the site.
- `NEXT_PUBLIC_SITE_URL` must be the same origin (canonicals, sitemap, OG URLs).
- Cookies are `httpOnly`, `SameSite=Lax` and `Secure` in production.
- `SANCTUM_STATEFUL_DOMAINS` only matters if you add cookie-based Sanctum flows;
  the dashboard uses a server-side Bearer token, so keep it minimal.

## 7. Files and backups

- `php artisan storage:link` exposes uploaded media under `/storage`.
- Backups live in `storage/app/backups` (private disk). Copy them off-machine:
  `aws s3 sync storage/app/backups s3://your-bucket/lumen/` in cron, or rclone.
- Restore from **Admin → Backups**; every restore stores a safety snapshot first.
- Keep `CMS_BACKUP_KEEP` (default 20) aligned with your disk budget.

## 8. SEO checklist at launch

- `NEXT_PUBLIC_SITE_URL` set to the canonical origin (https, no trailing slash).
- `/robots.txt` disallows `/admin`, `/api/`, and search/password query strings.
- `/sitemap.xml` lists every post/page/category/tag; submit it in Search Console.
- Every page has one `h1`, a unique title, a meta description and a self-canonical
  (audit: `node scripts/seo-audit.mjs https://www.example.com`).
- JSON-LD: WebSite + Organization site-wide, BlogPosting + BreadcrumbList per post.
- `og:image` falls back to the featured image; recommended 1200×630.
- Admin pages send `robots: noindex, nofollow` and are disallowed in robots.txt.
- RSS at `/feed.xml`; verify it validates.

## 9. Performance checklist

- Next.js static output is served from `/_next/static` with immutable caching.
- Public content is cached with `use cache` + tag revalidation on writes.
- API indexes cover post type/status/published_at, comment post/status/date and
  taxonomy lookups (see migrations).
- Featured images use `next/image` (resizing + modern formats) with the API
  host whitelisted in `next.config.mjs`.
- Enable OPcache with `validate_timestamps=0` and restart FPM per deploy.
- Add Redis for cache/sessions at scale; the dashboard talks to the API over
  loopback or a private network, not the public internet.

## 10. Monitoring, updates, rollback

- Uptime monitors: `https://api.example.com/api/v1/health` and
  `https://www.example.com/api/health` (both 200/503).
- Log targets: `api/storage/logs/laravel.log`, `next start` stdout.
- Deploy: `git pull` → composer/npm ci → migrate → build → restart workers →
  `php artisan optimize` → reload FPM.
- Rollback: restore the previous release directory, `php artisan optimize:clear`,
  restart. Database rollback uses Admin → Backups (data only; schema stays with
  migrations).
- Update dependencies deliberately (`composer update --with-dependencies`,
  `npm outdated`) and re-run `php artisan test` and `npm run build` first.

## 11. Pre-launch smoke test

1. `/` loads, one `h1`, no console errors.
2. `/admin/login` → sign in → dashboard loads.
3. Publish a post with the block editor; check `/posts/<slug>` and `/sitemap.xml`.
4. Leave a comment; moderate it in `/admin/comments`.
5. Upload media; confirm the public URL under `/storage`.
6. Create a backup, download it, restore it.
7. Confirm `/admin` returns `noindex` and robots.txt disallows it.
8. Run `php artisan test` (API) and `npm run lint && npm run build` (web).
