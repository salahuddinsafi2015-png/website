import { Suspense } from "react";

import Pagination from "@/components/Pagination";
import PostCard from "@/components/PostCard";
import { getPosts } from "@/lib/content";

export async function generateMetadata({ searchParams }) {
  const params = await searchParams;
  const page = Math.max(1, Number(params?.page || 1));
  const search = typeof params?.search === "string" ? params.search.trim() : "";

  return {
    title: search
      ? `Search results for “${search}”`
      : page > 1
        ? `All posts — page ${page}`
        : "All posts",
    description: search
      ? `Published posts matching “${search}”.`
      : "Every story published on this site.",
    alternates: {
      canonical: search ? "/posts" : page > 1 ? `/posts?page=${page}` : "/posts",
    },
    // Search result pages should not compete with the archive in the index.
    robots: search ? { index: false, follow: true } : undefined,
  };
}

async function PostResults({ searchParams }) {
  const params = await searchParams;
  const page = Math.max(1, Number(params?.page || 1));
  const search = typeof params?.search === "string" ? params.search : undefined;

  const { items, meta } = await getPosts({ page, search });

  if (!items.length) {
    return (
      <p className="rounded-lg border border-dashed border-border-subtle p-8 text-sm text-muted">
        {search ? `No posts match “${search}”.` : "No posts published yet."}
      </p>
    );
  }

  return (
    <>
      <div className="grid gap-6 md:grid-cols-2">
        {items.map((post) => (
          <PostCard key={post.id} post={post} />
        ))}
      </div>
      <Pagination meta={meta} basePath="/posts" params={{ search }} />
    </>
  );
}

export default function PostsPage({ searchParams }) {
  return (
    <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
      <h1 className="text-3xl font-semibold tracking-tight">All posts</h1>
      <form action="/posts" className="mt-6 flex max-w-md gap-2">
        <label htmlFor="search" className="sr-only">
          Search posts
        </label>
        <input
          id="search"
          name="search"
          type="search"
          placeholder="Search posts…"
          className="flex-1 rounded-md border border-border-subtle bg-background px-3 py-2 text-sm"
        />
        <button
          type="submit"
          className="rounded-md border border-border-subtle px-4 py-2 text-sm hover:border-accent hover:text-accent"
        >
          Search
        </button>
      </form>

      <div className="mt-8">
        <Suspense
          fallback={
            <div className="grid gap-6 md:grid-cols-2" aria-hidden="true">
              {[0, 1, 2, 3].map((key) => (
                <div key={key} className="h-64 animate-pulse rounded-xl bg-surface" />
              ))}
            </div>
          }
        >
          <PostResults searchParams={searchParams} />
        </Suspense>
      </div>
    </div>
  );
}
