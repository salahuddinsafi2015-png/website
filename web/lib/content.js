import "server-only";

import { cacheLife, cacheTag } from "next/cache";

import { ApiError, request, toQuery } from "./api";

const POSTS_PER_PAGE = Number(process.env.POSTS_PER_PAGE || 9);

export async function getSettings() {
  "use cache";
  cacheLife("hours");
  cacheTag("settings");

  try {
    const payload = await request("/settings");

    return payload?.data || {};
  } catch {
    return {};
  }
}

export async function getSiteName() {
  const settings = await getSettings();

  return settings["site.name"] || process.env.NEXT_PUBLIC_SITE_NAME || "Lumen CMS";
}

export async function getPosts({
  page = 1,
  perPage = POSTS_PER_PAGE,
  type = "post",
  category,
  tag,
  search,
} = {}) {
  "use cache";
  cacheLife("minutes");
  cacheTag("posts");

  const payload = await request(
    `/posts${toQuery({ page, per_page: perPage, type, category, tag, search })}`,
  );

  return {
    items: payload?.data || [],
    meta: payload?.meta || { current_page: 1, last_page: 1, total: 0 },
  };
}

export async function getPost(slug) {
  "use cache";
  cacheLife("minutes");
  cacheTag("posts", `post:${slug}`);

  try {
    const payload = await request(`/posts/${encodeURIComponent(slug)}`);

    return { post: payload?.data || null, locked: false };
  } catch (error) {
    if (error instanceof ApiError && error.status === 403) {
      return { post: null, locked: true };
    }

    throw error;
  }
}

/**
 * Password-protected posts are fetched without caching: the password must not
 * become part of a shared cache key.
 */
export async function getProtectedPost(slug, password) {
  try {
    const payload = await request(
      `/posts/${encodeURIComponent(slug)}${toQuery({ password })}`,
      { cache: "no-store" },
    );

    return { post: payload?.data || null, locked: false };
  } catch (error) {
    if (error instanceof ApiError && error.status === 403) {
      return { post: null, locked: true, failed: true };
    }

    throw error;
  }
}

export async function getPage(slug) {
  "use cache";
  cacheLife("hours");
  cacheTag("posts", `post:${slug}`);

  const payload = await request(
    `/posts/${encodeURIComponent(slug)}${toQuery({ type: "page" })}`,
  );

  return payload?.data || null;
}

export async function getTerm(taxonomy, slug) {
  "use cache";
  cacheLife("hours");
  cacheTag("terms");

  const payload = await request(`/terms/${taxonomy}/${encodeURIComponent(slug)}`);

  return payload?.data || null;
}

export async function getTerms(taxonomy = "category") {
  "use cache";
  cacheLife("hours");
  cacheTag("terms");

  const payload = await request(`/terms${toQuery({ taxonomy })}`);

  return payload?.data || [];
}

export async function getMenu(location) {
  "use cache";
  cacheLife("hours");
  cacheTag("menus");

  try {
    const payload = await request(`/menus/${location}`);

    return payload?.data || null;
  } catch {
    // A missing or unassigned menu falls back to category navigation.
    return null;
  }
}

export async function getWidgets(area) {
  "use cache";
  cacheLife("minutes");
  cacheTag("widgets");

  try {
    const payload = await request(`/widgets${toQuery({ area })}`);

    return payload?.data || [];
  } catch {
    return [];
  }
}

export async function getComments(slug) {
  "use cache";
  cacheLife("minutes");
  cacheTag("comments", `post:${slug}`);

  try {
    const payload = await request(`/posts/${encodeURIComponent(slug)}/comments`);

    return payload?.data || [];
  } catch {
    return [];
  }
}

export { POSTS_PER_PAGE };
