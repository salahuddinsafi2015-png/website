"use client";

import { useActionState } from "react";

import {
  createBackupAction,
  deleteBackupAction,
  restoreBackupAction,
} from "@/lib/actions";

const KIND_LABELS = {
  database: "Database",
  media: "Media library",
  full: "Full site",
};

function formatBytes(bytes) {
  const value = Number(bytes) || 0;

  if (value >= 1024 ** 3) return `${(value / 1024 ** 3).toFixed(2)} GB`;
  if (value >= 1024 ** 2) return `${(value / 1024 ** 2).toFixed(1)} MB`;
  if (value >= 1024) return `${Math.round(value / 1024)} KB`;

  return `${value} B`;
}

function formatDate(value) {
  if (!value) return "—";

  return new Date(value).toLocaleString();
}

export default function BackupManager({ backups, meta }) {
  const [state, formAction, pending] = useActionState(createBackupAction, {});

  return (
    <div className="flex flex-col gap-6">
      <form
        action={formAction}
        className="rounded-xl border border-border-subtle bg-surface/40 p-4"
      >
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">Contents</span>
            <select
              name="kind"
              defaultValue="full"
              className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
            >
              {Object.entries(KIND_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-1 flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">
              Note (optional)
            </span>
            <input
              name="note"
              maxLength={255}
              placeholder="Before the theme update"
              className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
            />
          </label>
          <button
            type="submit"
            disabled={pending}
            className="rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast disabled:opacity-60"
          >
            {pending ? "Creating…" : "Create backup"}
          </button>
        </div>

        {state?.error && (
          <p
            role="alert"
            className="mt-3 rounded-md border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400"
          >
            {state.error}
          </p>
        )}

        {state?.success && (
          <p
            role="status"
            className="mt-3 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-400"
          >
            {state.success}
          </p>
        )}
      </form>

      <section className="rounded-xl border border-border-subtle">
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border-subtle px-4 py-3 text-sm">
          <h2 className="font-semibold">Saved snapshots</h2>
          <p className="text-xs text-muted">
            {backups.length} stored · {formatBytes(meta?.total_size)}
            {meta?.free_space ? ` · ${formatBytes(meta.free_space)} free on disk` : ""}
          </p>
        </div>

        {backups.length === 0 ? (
          <p className="px-4 py-6 text-sm text-muted">
            No backups yet. Create one before making large changes.
          </p>
        ) : (
          <ul className="divide-y divide-border-subtle">
            {backups.map((backup) => (
              <li
                key={backup.id}
                className="flex flex-col gap-3 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="min-w-0">
                  <p className="flex flex-wrap items-center gap-2 font-medium">
                    <span className="rounded bg-background px-2 py-0.5 text-xs uppercase tracking-wide">
                      {KIND_LABELS[backup.kind] || backup.kind}
                    </span>
                    <time dateTime={backup.created_at}>{formatDate(backup.created_at)}</time>
                    <span className="text-xs font-normal text-muted">
                      {formatBytes(backup.size)}
                    </span>
                  </p>
                  <p className="mt-1 truncate text-xs text-muted">
                    {backup.note || backup.filename}
                  </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  <a
                    href={`/api/backups/${backup.id}/download`}
                    className="rounded-md border border-border-subtle px-3 py-1.5 text-xs hover:border-accent hover:text-accent"
                  >
                    Download
                  </a>

                  <form
                    action={restoreBackupAction}
                    onSubmit={(event) => {
                      if (
                        !window.confirm(
                          "Restore this snapshot? The current state is saved automatically first.",
                        )
                      ) {
                        event.preventDefault();
                      }
                    }}
                  >
                    <input type="hidden" name="id" value={backup.id} />
                    <button
                      type="submit"
                      className="rounded-md border border-border-subtle px-3 py-1.5 text-xs hover:border-accent hover:text-accent"
                    >
                      Restore
                    </button>
                  </form>

                  <form
                    action={deleteBackupAction}
                    onSubmit={(event) => {
                      if (!window.confirm("Delete this backup file permanently?")) {
                        event.preventDefault();
                      }
                    }}
                  >
                    <input type="hidden" name="id" value={backup.id} />
                    <button
                      type="submit"
                      className="rounded-md border border-red-500/40 px-3 py-1.5 text-xs text-red-600 hover:border-red-500 dark:text-red-400"
                    >
                      Delete
                    </button>
                  </form>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="rounded-xl border border-border-subtle p-4 text-sm text-muted">
        <h2 className="font-semibold text-foreground">How restores work</h2>
        <ul className="mt-2 list-disc space-y-1 pl-5">
          <li>Database snapshots restore content data; the schema stays managed by migrations.</li>
          <li>Sessions, queued jobs and caches are never included — they are rebuilt automatically.</li>
          <li>Each restore verifies the snapshot checksum first and refuses a damaged file.</li>
          <li>Newest 20 backups are kept; older files are pruned automatically.</li>
        </ul>
      </section>
    </div>
  );
}
