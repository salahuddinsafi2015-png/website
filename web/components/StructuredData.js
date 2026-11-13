/**
 * JSON-LD helpers for search engines.
 *
 * Every payload is serialised with `<` escaped so user-authored titles can
 * never break out of the script tag.
 */
function JsonLd({ data }) {
  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{
        __html: JSON.stringify(data).replace(/</g, "\\u003c"),
      }}
    />
  );
}

function absolute(url, base) {
  if (!url) return undefined;

  try {
    return new URL(url, base).href;
  } catch {
    return undefined;
  }
}

/** WebSite + Organization graph, emitted once from the root layout. */
export function SiteSchema({ siteName, siteUrl, description }) {
  return (
    <JsonLd
      data={{
        "@context": "https://schema.org",
        "@graph": [
          {
            "@type": "WebSite",
            "@id": `${siteUrl}/#website`,
            url: siteUrl,
            name: siteName,
            description,
            inLanguage: "en",
            publisher: { "@id": `${siteUrl}/#organization` },
            potentialAction: {
              "@type": "SearchAction",
              target: {
                "@type": "EntryPoint",
                urlTemplate: `${siteUrl}/posts?search={search_term_string}`,
              },
              "query-input": "required name=search_term_string",
            },
          },
          {
            "@type": "Organization",
            "@id": `${siteUrl}/#organization`,
            name: siteName,
            url: siteUrl,
            description,
          },
        ],
      }}
    />
  );
}

/** BlogPosting schema for a published article. */
export function PostSchema({ post, siteUrl, siteName }) {
  const path = post.path || `/posts/${post.slug}`;
  const url = absolute(path, siteUrl);
  const image =
    absolute(post.og_image?.url || post.featured_media?.url, siteUrl) || undefined;
  const authorName = post.author?.display_name || post.author?.name || siteName;

  return (
    <JsonLd
      data={{
        "@context": "https://schema.org",
        "@type": "BlogPosting",
        "@id": `${url}#article`,
        mainEntityOfPage: { "@type": "WebPage", "@id": url },
        headline: post.seo_title || post.title,
        description: post.seo_description || post.excerpt || undefined,
        image: image ? [image] : undefined,
        datePublished: post.published_at || post.created_at || undefined,
        dateModified: post.updated_at || post.published_at || undefined,
        inLanguage: "en",
        author: { "@type": "Person", name: authorName, url: siteUrl },
        publisher: {
          "@type": "Organization",
          name: siteName,
          logo: { "@type": "ImageObject", url: `${siteUrl}/favicon.ico` },
        },
        articleSection: (post.categories || []).map((term) => term.name),
        keywords: (post.tags || []).map((term) => term.name).join(", ") || undefined,
        wordCount: post.content
          ? post.content.replace(/<[^>]+>/g, " ").trim().split(/\s+/).length
          : undefined,
        isAccessibleForFree: !post.is_password_protected,
      }}
    />
  );
}

/** BreadcrumbList for archive and article pages. */
export function BreadcrumbSchema({ items }) {
  return (
    <JsonLd
      data={{
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        itemListElement: items.map((item, index) => ({
          "@type": "ListItem",
          position: index + 1,
          name: item.name,
          item: item.url,
        })),
      }}
    />
  );
}
