export const instant = false;

import Link from "next/link";

import MenuForm from "@/components/admin/MenuForm";
import MenuItemManager from "@/components/admin/MenuItemManager";
import { getMenu, getMenus, getPosts, getTerms } from "@/lib/admin-api";
import { requireToken } from "@/lib/session";

export default async function MenusPage({ searchParams }) {
  const token = await requireToken();
  const params = await searchParams;

  const menusPayload = await getMenus(token);
  const menus = menusPayload?.data || [];
  const locations = menusPayload?.meta?.locations || {};

  const selectedId = params?.menu || menus[0]?.id;
  const menu = selectedId ? await getMenu(token, String(selectedId)) : null;

  const [postsPayload, pagesPayload, categoriesPayload] = menu
    ? await Promise.all([
        getPosts(token, { type: "post", per_page: 50 }),
        getPosts(token, { type: "page", per_page: 50 }),
        getTerms(token, { taxonomy: "category" }),
      ])
    : [{ data: [] }, { data: [] }, { data: [] }];

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Menus</h1>
        <p className="mt-1 text-sm text-muted">
          Menus fill the theme&rsquo;s locations. Links to posts, pages and categories
          follow the content when its slug changes.
        </p>
      </header>

      <div className="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <aside className="flex flex-col gap-4">
          <nav aria-label="Menus" className="rounded-xl border border-border-subtle p-3">
            <p className="px-1 text-xs uppercase tracking-widest text-muted">Your menus</p>
            <ul className="mt-2 flex flex-col gap-1 text-sm">
              {menus.length ? (
                menus.map((entry) => (
                  <li key={entry.id}>
                    <Link
                      href={`/admin/menus?menu=${entry.id}`}
                      className={`flex items-center justify-between rounded-md px-2 py-1.5 ${
                        String(entry.id) === String(selectedId)
                          ? "bg-surface text-accent"
                          : "hover:bg-surface"
                      }`}
                    >
                      <span>{entry.name}</span>
                      <span className="text-xs text-muted">{entry.items_count ?? 0}</span>
                    </Link>
                    {entry.location && (
                      <p className="px-2 pb-1 text-xs text-muted">{entry.location}</p>
                    )}
                  </li>
                ))
              ) : (
                <li className="px-2 py-1 text-muted">No menus yet.</li>
              )}
            </ul>
          </nav>

          <MenuForm menu={menu} locations={locations} />
        </aside>

        <section className="min-w-0">
          {menu ? (
            <MenuItemManager
              menu={menu}
              posts={postsPayload?.data || []}
              pages={pagesPayload?.data || []}
              categories={categoriesPayload?.data || []}
            />
          ) : (
            <p className="rounded-lg border border-dashed border-border-subtle p-8 text-center text-sm text-muted">
              Create a menu to start adding links.
            </p>
          )}
        </section>
      </div>
    </div>
  );
}