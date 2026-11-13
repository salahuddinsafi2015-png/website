export const instant = false;

import TermForm from "@/components/admin/TermForm";
import { getTerms } from "@/lib/admin-api";
import { deleteTermAction } from "@/lib/actions";
import { requireToken } from "@/lib/session";

export default async function TermsPage() {
  const token = await requireToken();

  const payload = await getTerms(token).catch(() => null);
  const terms = payload?.data || [];

  const categories = terms.filter((term) => term.taxonomy === "category");
  const tags = terms.filter((term) => term.taxonomy === "tag");

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Taxonomy</h1>
        <p className="mt-1 text-sm text-muted">
          Categories are hierarchical; tags are flat. Counts reflect published posts.
        </p>
      </header>

      <TermForm categories={categories} />

      <div className="grid gap-6 lg:grid-cols-2">
        {[
          ["Categories", categories],
          ["Tags", tags],
        ].map(([label, list]) => (
          <section key={label} className="rounded-xl border border-border-subtle p-4">
            <h2 className="text-sm font-semibold">{label}</h2>
            <ul className="mt-3 divide-y divide-border-subtle text-sm">
              {list.length ? (
                list.map((term) => (
                  <li key={term.id} className="flex items-center justify-between gap-3 py-2">
                    <div>
                      <p className="font-medium">{term.name}</p>
                      <p className="text-xs text-muted">
                        /{term.slug} · {term.posts_count ?? term.count} posts
                      </p>
                    </div>
                    <form action={deleteTermAction}>
                      <input type="hidden" name="id" value={term.id} />
                      <button
                        type="submit"
                        className="rounded border border-red-500/40 px-2 py-1 text-xs text-red-600 hover:bg-red-500/10 dark:text-red-400"
                      >
                        Delete
                      </button>
                    </form>
                  </li>
                ))
              ) : (
                <li className="py-2 text-muted">Nothing here yet.</li>
              )}
            </ul>
          </section>
        ))}
      </div>
    </div>
  );
}