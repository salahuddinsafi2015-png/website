export const instant = false;

import Link from "next/link";

import { getPosts } from "@/lib/admin-api";
import {
  destroyPostAction,
  restorePostAction,
  trashPostAction,
} from "@/lib/actions";
import { requireToken } from "@/lib/session";

const STATUS_OPTIONS = ["", "publish", "draft", "pending", "future", "private", "trash"];

export default async function AdminPostsPage({ searchParams }) {
  const token = await requireToken();
  const params = await searchParams;

  const filters = {
    status: typeof params?.status === "string" ? params.status : "",
    type: typeof params?.type === "string" ? params.type : "",
    search: typeof params?.search === "string" ? params.search : "",
    trashed: params?.status === "trash" ? "with" : "",
    page: Number(params?.page || 1),
  };

  const payload = await getPosts(token, {
    status: filters.status || undefined,
    type: filters.type || undefined,
    search: filters.search || undefined,
    trashed: filters.trashed || undefined,
    page: filters.page,
  }).catch(() => null);

  const posts = payload?.data || [];
  const meta = payload?.meta || {};

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Posts</h1>
          <p className="mt-1 text-sm text-muted">
            {meta.total ?? posts.length} items
            {params?.saved ? " · saved" : ""}
          </p>
        </div>
        <Link
          href="/admin/posts/new"
          className="rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast"
        >
          New post
        </Link>
      </header>

      <form className="flex flex-wrap items-end gap-3 rounded-xl border border-border-subtle bg-surface/40 p-4">
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Search</span>
          <input
            name="search"
            defaultValue={filters.search}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
            placeholder="Title or content"
          />
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Status</span>
          <select
            name="status"
            defaultValue={filters.status}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          >
            {STATUS_OPTIONS.map((status) => (
              <option key={status || "all"} value={status}>
                {status || "All statuses"}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Type</span>
          <select
            name="type"
            defaultValue={filters.type}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          >
            <option value="">All types</option>
            <option value="post">Posts</option>
            <option value="page">Pages</option>
          </select>
        </label>
        <button
          type="submit"
          className="rounded-md border border-border-subtle px-4 py-1.5 text-sm hover:border-accent hover:text-accent"
        >
          Filter
        </button>
      </form>

      {payload ? (
        <div className="overflow-x-auto rounded-xl border border-border-subtle">
          <table className="w-full min-w-[640px] text-left text-sm">
            <thead className="bg-surface/60 text-xs uppercase tracking-wide text-muted">
              <tr>
                <th className="px-4 py-3 font-medium">Title</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Type</th>
                <th className="px-4 py-3 font-medium">Author</th>
                <th className="px-4 py-3 font-medium">Updated</th>
                <th className="px-4 py-3 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border-subtle">
              {posts.length ? (
                posts.map((post) => (
                  <tr key={post.id}>
                    <td className="px-4 py-3">
                      <Link href={`/admin/posts/${post.id}`} className="font-medium hover:text-accent">
                        {post.title || "(untitled)"}
                      </Link>
                      <p className="text-xs text-muted">/{post.slug}</p>
                    </td>
                    <td className="px-4 py-3 capitalize">{post.status}</td>
                    <td className="px-4 py-3 capitalize">{post.type}</td>
                    <td className="px-4 py-3">
                      {post.author?.display_name || post.author?.name || "—"}
                    </td>
                    <td className="px-4 py-3 text-muted">
                      {new Date(post.updated_at).toLocaleDateString("en-US", {
                        month: "short",
                        day: "numeric",
                        year: "numeric",
                      })}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex gap-2">
                        <Link
                          href={`/admin/posts/${post.id}`}
                          className="rounded border border-border-subtle px-2 py-1 text-xs hover:border-accent"
                        >
                          Edit
                        </Link>
                        {post.status === "trash" ? (
                          <>
                            <form action={restorePostAction}>
                              <input type="hidden" name="id" value={post.id} />
                              <button
                                type="submit"
                                className="rounded border border-border-subtle px-2 py-1 text-xs hover:border-accent"
                              >
                                Restore
                              </button>
                            </form>
                            <form action={destroyPostAction}>
                              <input type="hidden" name="id" value={post.id} />
                              <button
                                type="submit"
                                className="rounded border border-red-500/40 px-2 py-1 text-xs text-red-600 hover:bg-red-500/10 dark:text-red-400"
                              >
                                Delete
                              </button>
                            </form>
                          </>
                        ) : (
                          <form action={trashPostAction}>
                            <input type="hidden" name="id" value={post.id} />
                            <button
                              type="submit"
                              className="rounded border border-border-subtle px-2 py-1 text-xs hover:border-red-500/60 hover:text-red-600 dark:hover:text-red-400"
                            >
                              Trash
                            </button>
                          </form>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-muted">
                    No posts match these filters.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      ) : (
        <p className="rounded-lg border border-red-500/40 bg-red-500/10 p-4 text-sm">
          Posts could not be loaded.
        </p>
      )}

      {meta.last_page > 1 && (
        <nav aria-label="Pagination" className="flex items-center justify-between text-sm">
          <span className="text-muted">
            Page {meta.current_page} of {meta.last_page}
          </span>
          <div className="flex gap-2">
            {meta.current_page > 1 && (
              <Link
                href={`/admin/posts?page=${meta.current_page - 1}`}
                className="rounded border border-border-subtle px-3 py-1.5 hover:border-accent"
              >
                Previous
              </Link>
            )}
            {meta.current_page < meta.last_page && (
              <Link
                href={`/admin/posts?page=${meta.current_page + 1}`}
                className="rounded border border-border-subtle px-3 py-1.5 hover:border-accent"
              >
                Next
              </Link>
            )}
          </div>
        </nav>
      )}
    </div>
  );
}