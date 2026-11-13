import { notFound } from "next/navigation";
import { Suspense } from "react";

import { getPage } from "@/lib/content";

export async function generateMetadata({ params }) {
  const { slug } = await params;

  try {
    const page = await getPage(slug);

    // Fall back to a trimmed, tag-stripped excerpt of the page body so every
    // page ships a meta description even when editors did not write one.
    const plain = (page?.content || "")
      .replace(/<[^>]+>/g, " ")
      .replace(/\s+/g, " ")
      .trim()
      .slice(0, 155);

    return {
      title: page?.seo_title || page?.title || slug,
      description: page?.seo_description || page?.excerpt || plain || undefined,
      alternates: { canonical: page?.path || `/${slug}` },
    };
  } catch {
    return { title: "Page not found" };
  }
}

async function PageContent({ params }) {
  const { slug } = await params;

  let page;

  try {
    page = await getPage(slug);
  } catch {
    notFound();
  }

  if (!page) {
    notFound();
  }

  return (
    <article className="mx-auto max-w-3xl px-4 py-12 sm:px-6">
      <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{page.title}</h1>
      <div
        className="prose-cms mt-8"
        dangerouslySetInnerHTML={{ __html: page.content || "" }}
      />
    </article>
  );
}

/**
 * The params promise is awaited inside the Suspense boundary so the route can
 * still be prerendered for known slugs and streamed for new ones.
 */
export default function CmsPage({ params }) {
  return (
    <Suspense
      fallback={
        <div className="mx-auto max-w-3xl px-4 py-12">
          <div className="h-64 animate-pulse rounded-xl bg-surface" />
        </div>
      }
    >
      <PageContent params={params} />
    </Suspense>
  );
}
