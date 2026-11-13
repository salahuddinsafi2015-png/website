"use client";

import { useEffect, useId, useActionState } from "react";
import { useRouter } from "next/navigation";

import { restoreRevisionAction } from "@/lib/actions";

const BTN =
  "rounded-md border border-border-subtle px-2.5 py-1 text-xs hover:border-accent disabled:opacity-50";

/**
 * One restore button per revision. Restoring snapshots the current state
 * first (server-side), so undo is always possible from history itself.
 */
export default function RevisionHistory({ postId, revisions }) {
  const router = useRouter();
  const headingId = useId();
  const [state, formAction, pending] = useActionState(restoreRevisionAction, {
    error: null,
    message: null,
  });

  useEffect(() => {
    if (state.message) {
      // A full reload is deliberate: EditorForm keeps its client state across
      // router.refresh(), and a plain reload can replay a stale router
      // payload, so bust the cache with a unique query instead.
      const timer = setTimeout(() => {
        const url = new URL(window.location.href);
        url.searchParams.set("restored", String(Date.now()));
        window.location.assign(url.toString());
      }, 700);

      return () => clearTimeout(timer);
    }

    return undefined;
  }, [state, router]);

  if (!revisions?.length) {
    return null;
  }

  return (
    <section aria-labelledby={headingId} className="rounded-xl border border-border-subtle p-4">
      <h2 id={headingId} className="text-sm font-semibold">
        History
      </h2>
      <p className="mt-1 text-xs text-muted">
        Restoring copies the title, content, blocks and meta of the chosen
        revision back onto the post. Your current version is snapshot first,
        so nothing is lost.
      </p>

      {state.error && (
        <p role="alert" className="mt-3 rounded-md border border-red-500/40 bg-red-500/10 px-3 py-2 text-xs text-red-600 dark:text-red-400">
          {state.error}
        </p>
      )}
      {state.message && (
        <p role="status" className="mt-3 rounded-md border border-green-500/40 bg-green-500/10 px-3 py-2 text-xs text-green-700 dark:text-green-400">
          {state.message}
        </p>
      )}

      <ul className="mt-3 flex flex-col divide-y divide-border-subtle">
        {revisions.map((revision) => (
          <li key={revision.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
            <span className="min-w-0">
              <span className="block truncate font-medium">
                {revision.title || "(untitled)"}
              </span>
              <span className="text-xs text-muted">
                <time dateTime={revision.created_at}>
                  {new Date(revision.created_at).toLocaleString("en-US")}
                </time>
                {revision.status ? ` · ${revision.status}` : ""}
                {revision.author?.display_name ? ` · ${revision.author.display_name}` : ""}
              </span>
            </span>
            <form action={formAction}>
              <input type="hidden" name="id" value={postId} />
              <input type="hidden" name="revision_id" value={revision.id} />
              <button type="submit" className={BTN} disabled={pending}>
                Restore
              </button>
            </form>
          </li>
        ))}
      </ul>
    </section>
  );
}
