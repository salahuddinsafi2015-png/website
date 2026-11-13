import { Suspense } from "react";

import Pagination from "@/components/Pagination";
import PostCard from "@/components/PostCard";
import { BreadcrumbSchema } from "@/components/StructuredData";
import { getPosts, getTerm } from "@/lib/content";

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || "http://localhost:3000";

export async function generateMetadata({ params }) {
  const { slug } = await params;

  try {
    const term = await getTerm("tag", slug);
    const name = term?.name || slug;

    return {
      title: `Tag: ${name}`,
      description: `Posts tagged ${name}.`,
      alternates: { canonical: `/tags/${slug}` },
      openGraph: { type: "website", title: `Tag: ${name}`, url: `/tags/${slug}` },
      twitter: { card: "summary", title: `Tag: ${name}` },
    };
  } catch {
    return { title: `Tag: ${slug}`, alternates: { canonical: `/tags/${slug}` } };
  }
}

async function TagHeader({ params }) {
  const { slug } = await params;
  const term = await getTerm("tag", slug);

  return (
    <header>
      <p className="text-sm font-medium uppercase tracking-widest text-accent">Tag</p>
      <h1 className="mt-2 text-3xl font-semibold tracking-tight">#{term.name}</h1>
      <p className="mt-2 text-sm text-muted">{term.count} published posts</p>
    </header>
  );
}

async function TagPosts({ params, searchParams }) {
  const { slug } = await params;
  const query = await searchParams;
  const page = Math.max(1, Number(query?.page || 1));
  const { items, meta } = await getPosts({ page, tag: slug });

  return (
    <>
      {items.length ? (
        <div className="grid gap-6 md:grid-cols-2">
          {items.map((post) => (
            <PostCard key={post.id} post={post} />
          ))}
        </div>
      ) : (
        <p className="rounded-lg border border-dashed border-border-subtle p-8 text-sm text-muted">
          No posts carry this tag yet.
        </p>
      )}
      <Pagination meta={meta} basePath={`/tags/${slug}`} />
    </>
  );
}

export default function TagPage({ params, searchParams }) {
  return (
    <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
      <Suspense fallback={<div className="h-20 animate-pulse rounded bg-surface" />}>
        <TagHeader params={params} />
      </Suspense>
      <div className="mt-10">
        <Suspense fallback={<div className="h-64 animate-pulse rounded-xl bg-surface" />}>
          <TagPosts params={params} searchParams={searchParams} />
        </Suspense>
      </div>
    </div>
  );
}
