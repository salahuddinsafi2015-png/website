import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";

import CommentForm from "@/components/CommentForm";
import CommentThread from "@/components/CommentThread";
import { BreadcrumbSchema, PostSchema } from "@/components/StructuredData";
import WidgetArea from "@/components/WidgetArea";
import { getComments, getPost, getProtectedPost, getSettings } from "@/lib/content";

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || "http://localhost:3000";

export async function generateMetadata({ params }) {
  const { slug } = await params;
  const { post, locked } = await getPost(slug);

  if (!post) {
    return {
      title: locked ? "Password protected" : "Post not found",
    };
  }

  const title = post.seo_title || post.title;
  const description = post.seo_description || post.excerpt || undefined;
  const image = post.og_image?.url || post.featured_media?.url;

  return {
    title,
    description,
    alternates: { canonical: post.path },
    openGraph: {
      type: "article",
      title,
      description,
      url: `${SITE_URL}${post.path}`,
      publishedTime: post.published_at || undefined,
      modifiedTime: post.updated_at || undefined,
      images: image ? [{ url: image, alt: post.title }] : undefined,
    },
    twitter: {
      card: image ? "summary_large_image" : "summary",
      title,
      description,
      images: image ? [image] : undefined,
    },
  };
}

function PostSkeleton() {
  return (
    <div className="mx-auto max-w-3xl animate-pulse space-y-4 px-4 py-12">
      <div className="h-4 w-24 rounded bg-surface" />
      <div className="h-10 w-3/4 rounded bg-surface" />
      <div className="h-64 rounded-xl bg-surface" />
      <div className="h-4 w-full rounded bg-surface" />
      <div className="h-4 w-5/6 rounded bg-surface" />
    </div>
  );
}

async function Article({ params, searchParams }) {
  const { slug } = await params;
  const query = await searchParams;
  const password = typeof query?.password === "string" ? query.password : null;

  const { post, locked } = password
    ? await getProtectedPost(slug, password)
    : await getPost(slug);

  if (locked) {
    return (
      <div className="mx-auto max-w-xl px-4 py-20">
        <h1 className="text-2xl font-semibold">This post is password protected</h1>
        <p className="mt-2 text-sm text-muted">
          Enter the password you were given to read the article.
        </p>
        <form method="get" className="mt-6 flex gap-2">
          <label htmlFor="password" className="sr-only">
            Password
          </label>
          <input
            id="password"
            name="password"
            type="password"
            autoComplete="off"
            required
            className="flex-1 rounded-md border border-border-subtle bg-background px-3 py-2"
          />
          <button
            type="submit"
            className="rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast"
          >
            Unlock
          </button>
        </form>
      </div>
    );
  }

  if (!post) {
    notFound();
  }

  const comments = post.comment_status === "open" ? await getComments(slug) : [];
  const category = post.categories?.[0];
  const published = post.published_at
    ? new Date(post.published_at).toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric",
      })
    : null;

  const settings = await getSettings();
  const siteName = settings["site.name"] || "Lumen CMS";
  const breadcrumbs = [
    { name: "Home", url: SITE_URL },
    ...(category
      ? [{ name: category.name, url: `${SITE_URL}/categories/${category.slug}` }]
      : []),
    { name: post.title, url: `${SITE_URL}${post.path}` },
  ];

  return (
    <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:grid lg:grid-cols-[minmax(0,1fr)_18rem] lg:gap-12">
      <article>
      <PostSchema post={post} siteUrl={SITE_URL} siteName={siteName} />
      <BreadcrumbSchema items={breadcrumbs} />

      <nav aria-label="Breadcrumb" className="text-sm text-muted">
        <Link href="/" className="hover:text-accent">
          Home
        </Link>
        {category && (
          <>
            <span aria-hidden="true"> / </span>
            <Link href={`/categories/${category.slug}`} className="hover:text-accent">
              {category.name}
            </Link>
          </>
        )}
      </nav>

      <header className="mt-4">
        <h1 className="text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">
          {post.title}
        </h1>
        <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
          {post.author && <span>{post.author.display_name || post.author.name}</span>}
          {published && (
            <time dateTime={post.published_at}>{published}</time>
          )}
          <span>{post.reading_time} min read</span>
        </div>
        {post.tags?.length > 0 && (
          <ul className="mt-4 flex flex-wrap gap-2">
            {post.tags.map((tag) => (
              <li key={tag.id}>
                <Link
                  href={`/tags/${tag.slug}`}
                  className="rounded-full border border-border-subtle px-3 py-1 text-xs hover:border-accent hover:text-accent"
                >
                  #{tag.name}
                </Link>
              </li>
            ))}
          </ul>
        )}
      </header>

      {post.featured_media?.is_image && (
        <Image
          src={post.featured_media.url}
          alt={post.featured_media.alt_text || post.title}
          width={post.featured_media.width || 1200}
          height={post.featured_media.height || 630}
          className="mt-8 w-full rounded-xl object-cover"
          priority
          sizes="(max-width: 768px) 100vw, 768px"
        />
      )}

      <div
        className="prose-cms mt-8"
        dangerouslySetInnerHTML={{ __html: post.content || "" }}
      />

      <section className="mt-14 border-t border-border-subtle pt-8" aria-labelledby="comments-heading">
        <h2 id="comments-heading" className="text-xl font-semibold">
          Comments ({comments.length})
        </h2>

        {post.comment_status === "open" ? (
          <>
            <div className="mt-4">
              <CommentThread comments={comments} />
            </div>
            <h3 className="mt-10 text-lg font-semibold">Leave a comment</h3>
            <div className="mt-3">
              <CommentForm slug={post.slug} />
            </div>
          </>
        ) : (
          <p className="mt-2 text-sm text-muted">Comments are closed for this post.</p>
        )}
      </section>
      </article>

      <aside className="mt-12 lg:mt-0" aria-label="Sidebar">
        <WidgetArea area="sidebar" />
      </aside>
    </div>
  );
}

export default function PostPage({ params, searchParams }) {
  return (
    <Suspense fallback={<PostSkeleton />}>
      <Article params={params} searchParams={searchParams} />
    </Suspense>
  );
}
