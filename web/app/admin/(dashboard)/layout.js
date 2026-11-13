import Link from "next/link";

import { getMe } from "@/lib/admin-api";
import { logoutAction } from "@/lib/actions";
import { requireToken } from "@/lib/session";

export const metadata = {
  title: "Dashboard",
  robots: { index: false, follow: false },
};

// The dashboard reads the session cookie and per-user data on every request,
// so it intentionally blocks on the server instead of prerendering a shell.
export const instant = false;

const NAV = [
  { href: "/admin", label: "Overview" },
  { href: "/admin/posts", label: "Posts" },
  { href: "/admin/media", label: "Media" },
  { href: "/admin/comments", label: "Comments" },
  { href: "/admin/terms", label: "Taxonomy" },
  { href: "/admin/menus", label: "Menus" },
  { href: "/admin/widgets", label: "Widgets" },
  { href: "/admin/appearance", label: "Appearance" },
  { href: "/admin/insights", label: "Insights" },
  { href: "/admin/users", label: "Users" },
  { href: "/admin/backups", label: "Backups" },
  { href: "/admin/settings", label: "Settings" },
];

export default async function DashboardLayout({ children }) {
  const token = await requireToken();
  const user = await getMe(token).catch(() => null);

  if (!user) {
    // A revoked or expired token cannot be cleared during render, so the
    // login page takes over and replaces the cookie on the next sign-in.
    return (
      <div className="mx-auto max-w-md px-4 py-20">
        <h1 className="text-xl font-semibold">Your session has expired</h1>
        <p className="mt-2 text-sm text-muted">
          Sign in again to continue managing content.
        </p>
        <Link
          href="/admin/login"
          className="mt-6 inline-block rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast"
        >
          Go to sign in
        </Link>
      </div>
    );
  }

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6 lg:flex-row">
      <aside className="lg:w-56 lg:shrink-0">
        <div className="rounded-xl border border-border-subtle bg-surface/40 p-4">
          <p className="text-xs uppercase tracking-widest text-muted">Signed in as</p>
          <p className="mt-1 font-medium">{user.display_name || user.name}</p>
          <p className="text-xs text-muted">
            {(user.roles || []).join(", ") || "no role"}
          </p>

          <nav aria-label="Dashboard" className="mt-4 flex flex-col gap-1 text-sm">
            {NAV.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className="rounded-md px-2 py-1.5 hover:bg-background hover:text-accent"
              >
                {item.label}
              </Link>
            ))}
          </nav>

          <form action={logoutAction} className="mt-4 border-t border-border-subtle pt-3">
            <button
              type="submit"
              className="w-full rounded-md border border-border-subtle px-3 py-1.5 text-sm hover:border-accent hover:text-accent"
            >
              Sign out
            </button>
          </form>

          <Link
            href="/"
            className="mt-2 block rounded-md px-2 py-1.5 text-sm hover:text-accent"
          >
            View site →
          </Link>
        </div>
      </aside>

      <div className="min-w-0 flex-1">{children}</div>
    </div>
  );
}
