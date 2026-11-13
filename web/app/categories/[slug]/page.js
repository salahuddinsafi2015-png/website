import { Suspense } from "react";

import Pagination from "@/components/Pagination";
import PostCard from "@/components/PostCard";
import { BreadcrumbSchema } from "@/components/StructuredData";
import { getPosts, getTerm } from "@/lib/content";

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || "http://localhost:3000";

// Reads the API-rendered breadcrumb above the streaming boundary.
export const instant = false;

export async function generateMetadata({ params }) {
  const { slug } = await params;

  try {
    const term = await getTerm("category", slug);
    const name = term?.name || slug;

    return {
      title: name,
      description: term?.description || `Posts filed under ${name}.`,
      alternates: { canonical: `/categories/${slug}` },
      openGraph: { type: "website", title: name, url: `/categories/${slug}` },
      twitter: { card: "summary", title: name },
    };
  } catch {
    return {
      title: "Category",
      alternates: { canonical: `/categories/${slug}` },
    };
  }
}

async function CategoryHeader({ params }) {
  const { slug } = await params;
  const term = await getTerm("category", slug);

  return (
    <header className="max-w-2xl">
      <p className="text-sm font-medium uppercase tracking-widest text-accent">Category</p>
      <h1 className="mt-2 text-3xl font-semibold tracking-tight">{term.name}</h1>
      {term.description && <p className="mt-3 text-muted">{term.description}</p>}
      <p className="mt-2 text-sm text-muted">{term.count} published posts</p>
    </header>
  );
}

async function CategoryPosts({ params, searchParams }) {
  const { slug } = await params;
  const query = await searchParams;
  const page = Math.max(1, Number(query?.page || 1));
  const { items, meta } = await getPosts({ page, category: slug });

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
          Nothing has been published in this category yet.
        </p>
      )}
      <Pagination meta={meta} basePath={`/categories/${slug}`} />
    </>
  );
}

export default async function CategoryPage({ params, searchParams }) {
  const { slug } = await params;

  return (
    <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
      <BreadcrumbSchema
        items={[
          { name: "Home", url: SITE_URL },
          { name: `Category: ${slug}`, url: `${SITE_URL}/categories/${slug}` },
        ]}
      />
      <Suspense fallback={<div className="h-24 animate-pulse rounded bg-surface" />}>
        <CategoryHeader params={params} />
      </Suspense>
      <div className="mt-10">
        <Suspense fallback={<div className="h-64 animate-pulse rounded-xl bg-surface" />}>
          <CategoryPosts params={params} searchParams={searchParams} />
        </Suspense>
      </div>
    </div>
  );
}
