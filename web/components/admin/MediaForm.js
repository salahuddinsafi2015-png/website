"use client";

import { useActionState } from "react";

import { uploadMediaAction } from "@/lib/actions";

export default function MediaForm() {
  const [state, formAction, pending] = useActionState(uploadMediaAction, {});

  return (
    <form
      action={formAction}
      encType="multipart/form-data"
      className="flex flex-col gap-3 rounded-xl border border-border-subtle bg-surface/40 p-4"
    >
      <h2 className="text-sm font-semibold">Upload media</h2>

      {state?.error && (
        <p
          role="alert"
          className="rounded-md border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400"
        >
          {state.error}
        </p>
      )}

      {state?.success && (
        <p
          role="status"
          className="rounded-md border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-400"
        >
          {state.success}
        </p>
      )}

      <label className="flex flex-col gap-1 text-sm">
        <span className="text-xs uppercase tracking-wide text-muted">File</span>
        <input
          type="file"
          name="file"
          required
          accept="image/jpeg,image/png,image/gif,image/webp,image/avif,application/pdf"
          className="rounded-md border border-border-subtle bg-background px-3 py-2 text-sm"
        />
        <span className="text-xs text-muted">
          JPEG, PNG, GIF, WebP, AVIF or PDF up to 5 MB. SVG is rejected by design.
        </span>
      </label>

      <div className="grid gap-3 sm:grid-cols-2">
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Title</span>
          <input
            name="title"
            maxLength={255}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          />
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Alt text</span>
          <input
            name="alt_text"
            maxLength={255}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          />
        </label>
      </div>

      <button
        type="submit"
        disabled={pending}
        className="self-start rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast disabled:opacity-60"
      >
        {pending ? "Uploading…" : "Upload"}
      </button>
    </form>
  );
}
