import Link from "next/link";

function buildHref(basePath, params, page) {
  const search = new URLSearchParams();

  Object.entries(params || {}).forEach(([key, value]) => {
    if (value) {
      search.set(key, String(value));
    }
  });

  if (page > 1) {
    search.set("page", String(page));
  } else {
    search.delete("page");
  }

  const query = search.toString();

  return query ? `${basePath}?${query}` : basePath;
}

export default function Pagination({ meta, basePath, params = {} }) {
  const current = Number(meta?.current_page || 1);
  const last = Number(meta?.last_page || 1);

  if (last <= 1) {
    return null;
  }

  return (
    <nav aria-label="Pagination" className="mt-10 flex items-center justify-between text-sm">
      {current > 1 ? (
        <Link
          className="rounded-md border border-border-subtle px-4 py-2 hover:border-accent hover:text-accent"
          href={buildHref(basePath, params, current - 1)}
          rel="prev"
        >
          ← Newer
        </Link>
      ) : (
        <span />
      )}
      <span className="text-muted">
        Page {current} of {last}
      </span>
      {current < last ? (
        <Link
          className="rounded-md border border-border-subtle px-4 py-2 hover:border-accent hover:text-accent"
          href={buildHref(basePath, params, current + 1)}
          rel="next"
        >
          Older →
        </Link>
      ) : (
        <span />
      )}
    </nav>
  );
}
