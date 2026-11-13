import Link from "next/link";
import { Suspense } from "react";

import PostCard from "@/components/PostCard";
import { getPosts, getSettings, getTerms, POSTS_PER_PAGE } from "@/lib/content";

export const metadata = {
  alternates: { canonical: "/" },
};

async function LatestPosts() {
  const { items } = await getPosts({ perPage: POSTS_PER_PAGE });

  if (!items.length) {
    return (
      <p className="rounded-lg border border-dashed border-border-subtle p-8 text-sm text-muted">
        No published posts yet. Sign in to the dashboard to publish your first story.
      </p>
    );
  }

  return (
    <div className="grid gap-6 md:grid-cols-2">
      {items.map((post) => (
        <PostCard key={post.id} post={post} />
      ))}
    </div>
  );
}

async function CategoryStrip() {
  const categories = await getTerms("category");

  if (!categories.length) {
    return null;
  }

  return (
    <div className="flex flex-wrap gap-2">
      {categories.map((category) => (
        <Link
          key={category.id}
          href={`/categories/${category.slug}`}
          className="rounded-full border border-border-subtle px-3 py-1 text-sm hover:border-accent hover:text-accent"
        >
          {category.name} · {category.count}
        </Link>
      ))}
    </div>
  );
}

function PostsSkeleton() {
  return (
    <div className="grid gap-6 md:grid-cols-2" aria-hidden="true">
      {[0, 1, 2, 3].map((key) => (
        <div key={key} className="h-64 animate-pulse rounded-xl bg-surface" />
      ))}
    </div>
  );
}

export default async function HomePage() {
  const settings = await getSettings();

  return (
    <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
      <section className="max-w-3xl">
        <p className="text-sm font-medium uppercase tracking-widest text-accent">
          {settings["site.tagline"] || "Publishing, simplified"}
        </p>
        <h1 className="mt-3 text-4xl font-semibold tracking-tight sm:text-5xl">
          {settings["site.name"] || "Lumen CMS"}
        </h1>
        <p className="mt-4 text-lg leading-relaxed text-muted">
          {settings["site.description"] ||
            "A WordPress-style CMS with a Laravel API and a Next.js frontend."}
        </p>
      </section>

      <section className="mt-8">
        <Suspense fallback={<div className="h-8" />}>
          <CategoryStrip />
        </Suspense>
      </section>

      <section className="mt-12" aria-labelledby="latest-heading">
        <div className="mb-6 flex items-end justify-between">
          <h2 id="latest-heading" className="text-2xl font-semibold tracking-tight">
            Latest posts
          </h2>
          <Link className="text-sm text-accent hover:underline" href="/posts">
            Browse all posts →
          </Link>
        </div>
        <Suspense fallback={<PostsSkeleton />}>
          <LatestPosts />
        </Suspense>
      </section>
    </div>
  );
}
