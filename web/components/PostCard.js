import Image from "next/image";
import Link from "next/link";

export default function PostCard({ post, featured = false }) {
  const category = post.categories?.[0];
  const date = post.published_at
    ? new Date(post.published_at).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
      })
    : null;

  return (
    <article
      className={`group flex flex-col overflow-hidden rounded-xl border border-border-subtle bg-surface/40 transition hover:border-accent/60 ${
        featured ? "md:col-span-2" : ""
      }`}
    >
      {post.featured_media?.is_image && (
        <Link href={post.path} className="block overflow-hidden">
          <Image
            src={post.featured_media.url}
            alt={post.featured_media.alt_text || post.title || ""}
            width={post.featured_media.width || 1200}
            height={post.featured_media.height || 630}
            className="h-48 w-full object-cover transition duration-300 group-hover:scale-[1.02]"
            sizes="(max-width: 768px) 100vw, 50vw"
          />
        </Link>
      )}
      <div className="flex flex-1 flex-col gap-3 p-5">
        <div className="flex flex-wrap items-center gap-2 text-xs uppercase tracking-wide text-muted">
          {category && (
            <Link href={`/categories/${category.slug}`} className="hover:text-accent">
              {category.name}
            </Link>
          )}
          {date && <time dateTime={post.published_at}>{date}</time>}
        </div>
        <h2 className="text-xl font-semibold leading-snug">
          <Link href={post.path} className="hover:text-accent">
            {post.title}
          </Link>
        </h2>
        {post.excerpt && (
          <p className="text-sm leading-relaxed text-muted">{post.excerpt}</p>
        )}
        <div className="mt-auto pt-2 text-sm text-muted">
          {post.author?.display_name || post.author?.name}
          {typeof post.comments_count === "number" && (
            <span className="ml-2">· {post.comments_count} comments</span>
          )}
        </div>
      </div>
    </article>
  );
}
