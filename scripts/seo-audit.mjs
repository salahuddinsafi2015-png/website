#!/usr/bin/env node
/**
 * SEO audit: crawls every URL in /sitemap.xml and validates the per-page
 * basics search engines rely on — status, title, meta description,
 * self-referencing canonical, exactly one <h1>, JSON-LD, and img alt text.
 *
 * Usage: node scripts/seo-audit.mjs http://localhost:3000
 * Exit code is non-zero when any error-level check fails.
 */

const base = (process.argv[2] || "http://127.0.0.1:3000").replace(/\/$/, "");

const errors = [];
const warnings = [];
let checked = 0;

function record(level, url, message) {
  (level === "error" ? errors : warnings).push(`${url} — ${message}`);
}

async function fetchText(url) {
  const response = await fetch(url, { redirect: "follow" });
  return { status: response.status, html: await response.text() };
}

function extract(html, pattern) {
  return [...html.matchAll(pattern)].map((m) => m[1]);
}

async function auditPage(url) {
  checked += 1;
  let result;

  try {
    result = await fetchText(url);
  } catch (error) {
    record("error", url, `fetch failed: ${error.message}`);
    return;
  }

  if (result.status !== 200) {
    record("error", url, `HTTP ${result.status}`);
    return;
  }

  const { html } = result;
  const titles = extract(html, /<title[^>]*>([^<]*)<\/title>/gi);
  const descriptions = extract(html, /<meta[^>]*name="description"[^>]*content="([^"]*)"/gi);
  const canonicals = extract(html, /<link[^>]*rel="canonical"[^>]*href="([^"]*)"/gi);
  const h1s = extract(html, /<h1[^>]*>[\s\S]*?<\/h1>/gi);
  const jsonLd = extract(html, /<script[^>]*type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/gi);
  const imgs = extract(html, /<img\b([^>]*)>/gi);

  if (!titles.length || !titles[0].trim()) record("error", url, "missing <title>");
  if (titles.length > 1) record("warning", url, `${titles.length} <title> tags`);
  if (!descriptions.length || !descriptions[0].trim()) record("error", url, "missing meta description");
  if (!canonicals.length) {
    record("error", url, "missing canonical");
  } else if (new URL(canonicals[0], base).pathname !== new URL(url).pathname) {
    record("error", url, `canonical points elsewhere: ${canonicals[0]}`);
  }
  if (h1s.length !== 1) record("error", url, `expected exactly 1 <h1>, found ${h1s.length}`);
  if (!jsonLd.length) record("warning", url, "no JSON-LD structured data");
  for (const tag of imgs) {
    if (!/alt="/.test(tag)) record("error", url, `img without alt: ${tag.slice(0, 80)}`);
  }
}

async function main() {
  const { html: sitemapXml } = await fetchText(`${base}/sitemap.xml`);
  const urls = extract(sitemapXml, /<loc>([^<]+)<\/loc>/g).map((u) => u.trim());

  if (!urls.length) {
    console.error(`No URLs found in ${base}/sitemap.xml`);
    process.exit(1);
  }

  console.log(`Auditing ${urls.length} URLs from /sitemap.xml …`);

  // The sitemap uses the public origin; rewrite it to the audit target so the
  // same audit works against local/preview hosts.
  const targetOrigin = new URL(base).origin;

  for (const raw of urls) {
    const parsed = new URL(raw);
    await auditPage(`${targetOrigin}${parsed.pathname}${parsed.search}`);
  }

  // Also audit the homepage, which is always self-canonical and not in the sitemap.
  await auditPage(`${base}/`);

  console.log(`\nChecked ${checked} pages: ${errors.length} errors, ${warnings.length} warnings.`);

  for (const item of errors) console.error(`  ERROR   ${item}`);
  for (const item of warnings) console.error(`  WARNING ${item}`);

  process.exit(errors.length ? 1 : 0);
}

main();
