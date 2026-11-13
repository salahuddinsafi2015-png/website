import Link from "next/link";

import { getPosts, getTerms, getWidgets } from "@/lib/content";

async function WidgetBody({ widget }) {
  if (widget.type === "rich_text") {
    return (
      <div
        className="prose-cms text-sm"
        dangerouslySetInnerHTML={{ __html: widget.text || "" }}
      />
    );
  }

  if (widget.type === "categories") {
    const categories = await getTerms("category");

    return (
      <ul className="flex flex-col gap-1 text-sm">
        {categories.map((category) => (
          <li key={category.id}>
            <Link
              href={`/categories/${category.slug}`}
              className="flex items-center justify-between hover:text-accent"
            >
              <span>{category.name}</span>
              <span className="text-xs text-muted">{category.count}</span>
            </Link>
          </li>
        ))}
      </ul>
    );
  }

  if (widget.type === "recent_posts") {
    const { items } = await getPosts({ perPage: 5 });

    return (
      <ul className="flex flex-col gap-2 text-sm">
        {items.map((post) => (
          <li key={post.id}>
            <Link href={post.path} className="hover:text-accent">
              {post.title}
            </Link>
          </li>
        ))}
      </ul>
    );
  }

  if (widget.type === "menu") {
    const items = widget.menu?.items || [];

    return (
      <ul className="flex flex-col gap-1 text-sm">
        {items
          .filter((item) => item.url)
          .map((item) => (
            <li key={item.id}>
              <Link href={item.url} className="hover:text-accent">
                {item.label}
              </Link>
            </li>
          ))}
      </ul>
    );
  }

  return null;
}

/**
 * Widgets are configured per area in the dashboard and rendered on the server.
 */
export default async function WidgetArea({ area, className = "" }) {
  const widgets = await getWidgets(area);

  if (!widgets.length) {
    return null;
  }

  return (
    <div className={`flex flex-col gap-6 ${className}`}>
      {widgets.map((widget) => (
        <section key={widget.id}>
          {widget.title && (
            <h2 className="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">
              {widget.title}
            </h2>
          )}
          <WidgetBody widget={widget} />
        </section>
      ))}
    </div>
  );
}
