export const instant = false;

import Link from "next/link";

import { getComments } from "@/lib/admin-api";
import { deleteCommentAction, moderateCommentAction } from "@/lib/actions";
import { requireToken } from "@/lib/session";

const FILTERS = [
  ["", "All"],
  ["pending", "Pending"],
  ["approved", "Approved"],
  ["spam", "Spam"],
  ["trash", "Trash"],
];

export default async function CommentsPage({ searchParams }) {
  const token = await requireToken();
  const params = await searchParams;
  const status = typeof params?.status === "string" ? params.status : "";

  const payload = await getComments(token, {
    status: status || undefined,
    page: Number(params?.page || 1),
  }).catch(() => null);

  const comments = payload?.data || [];

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Comments</h1>
        <p className="mt-1 text-sm text-muted">Approve, reject and delete visitor comments.</p>
      </header>

      <nav className="flex flex-wrap gap-2 text-sm">
        {FILTERS.map(([value, label]) => (
          <Link
            key={label}
            href={value ? `/admin/comments?status=${value}` : "/admin/comments"}
            className={`rounded-full border px-3 py-1 ${
              status === value
                ? "border-accent text-accent"
                : "border-border-subtle hover:border-accent"
            }`}
          >
            {label}
          </Link>
        ))}
      </nav>

      <ul className="flex flex-col gap-3">
        {comments.length ? (
          comments.map((comment) => (
            <li
              key={comment.id}
              className="rounded-xl border border-border-subtle bg-surface/40 p-4"
            >
              <div className="flex flex-wrap items-baseline justify-between gap-2">
                <p className="text-sm font-semibold">
                  {comment.author_name}
                  <span className="ml-2 text-xs font-normal uppercase tracking-wide text-muted">
                    {comment.status}
                  </span>
                </p>
                <time className="text-xs text-muted" dateTime={comment.created_at}>
                  {new Date(comment.created_at).toLocaleString("en-US")}
                </time>
              </div>
              <p className="mt-2 whitespace-pre-line text-sm">{comment.content}</p>

              <div className="mt-3 flex flex-wrap gap-2">
                {["approved", "pending", "spam", "trash"].map((next) => (
                  <form key={next} action={moderateCommentAction}>
                    <input type="hidden" name="id" value={comment.id} />
                    <input type="hidden" name="status" value={next} />
                    <button
                      type="submit"
                      className="rounded border border-border-subtle px-2 py-1 text-xs capitalize hover:border-accent hover:text-accent"
                    >
                      {next}
                    </button>
                  </form>
                ))}
                <form action={deleteCommentAction}>
                  <input type="hidden" name="id" value={comment.id} />
                  <button
                    type="submit"
                    className="rounded border border-red-500/40 px-2 py-1 text-xs text-red-600 hover:bg-red-500/10 dark:text-red-400"
                  >
                    Delete
                  </button>
                </form>
              </div>
            </li>
          ))
        ) : (
          <li className="rounded-lg border border-dashed border-border-subtle p-8 text-center text-sm text-muted">
            No comments in this view.
          </li>
        )}
      </ul>
    </div>
  );
}