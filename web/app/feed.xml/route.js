import { getPosts, getSettings } from "@/lib/content";

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || "http://localhost:3000";

function escapeXml(value) {
  return String(value || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&apos;");
}

export async function GET() {
  const [settings, { items }] = await Promise.all([
    getSettings(),
    getPosts({ perPage: 20 }),
  ]);

  const name = settings["site.name"] || "Lumen CMS";
  const description = settings["site.description"] || "Latest posts";

  const entries = items
    .map((post) => {
      const url = `${SITE_URL}${post.path}`;
      const published = post.published_at ? new Date(post.published_at).toUTCString() : "";

      return `    <item>
      <title>${escapeXml(post.title)}</title>
      <link>${escapeXml(url)}</link>
      <guid isPermaLink="true">${escapeXml(url)}</guid>
      <pubDate>${published}</pubDate>
      <description>${escapeXml(post.excerpt || "")}</description>
    </item>`;
    })
    .join("\n");

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>${escapeXml(name)}</title>
    <link>${escapeXml(SITE_URL)}</link>
    <description>${escapeXml(description)}</description>
    <language>en</language>
${entries}
  </channel>
</rss>`;

  return new Response(xml, {
    headers: {
      "Content-Type": "application/rss+xml; charset=utf-8",
      "Cache-Control": "public, max-age=300, s-maxage=600",
    },
  });
}
