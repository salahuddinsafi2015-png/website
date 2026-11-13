import { getPosts, getTerms } from "@/lib/content";

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || "http://localhost:3000";
const MAX_PAGES = 50;

/**
 * Walk every published post page instead of only the first one, so archives
 * larger than a single API page still appear in the sitemap.
 */
async function allPosts() {
  const collected = [];
  let page = 1;
  let lastPage = 1;

  do {
    const { items, meta } = await getPosts({ page, perPage: 50 });
    collected.push(...items);
    lastPage = Math.min(Number(meta?.last_page) || 1, MAX_PAGES);
    page += 1;
  } while (page <= lastPage);

  return collected;
}

export default async function sitemap() {
  const [posts, pages, categories, tags] = await Promise.all([
    allPosts(),
    getPosts({ type: "page", perPage: 50 }),
    getTerms("category"),
    getTerms("tag"),
  ]);

  const articleEntries = posts
    .filter((post) => post.type !== "page" && !post.is_password_protected)
    .map((post) => ({
      url: `${SITE_URL}${post.path}`,
      lastModified: post.updated_at ? new Date(post.updated_at) : undefined,
      changeFrequency: "weekly",
      priority: post.is_sticky ? 0.9 : 0.7,
    }));

  const pageEntries = (pages.items || [])
    .filter((page) => !page.is_password_protected)
    .map((page) => ({
      url: `${SITE_URL}${page.path}`,
      lastModified: page.updated_at ? new Date(page.updated_at) : undefined,
      changeFrequency: "monthly",
      priority: 0.6,
    }));

  return [
    { url: SITE_URL, changeFrequency: "daily", priority: 1 },
    { url: `${SITE_URL}/posts`, changeFrequency: "daily", priority: 0.8 },
    ...articleEntries,
    ...pageEntries,
    ...categories.map((category) => ({
      url: `${SITE_URL}/categories/${category.slug}`,
      lastModified: category.updated_at ? new Date(category.updated_at) : undefined,
      changeFrequency: "weekly",
      priority: 0.5,
    })),
    ...tags.map((tag) => ({
      url: `${SITE_URL}/tags/${tag.slug}`,
      lastModified: tag.updated_at ? new Date(tag.updated_at) : undefined,
      changeFrequency: "monthly",
      priority: 0.4,
    })),
  ];
}
