"use client";

import { useActionState } from "react";

import { submitCommentAction } from "@/lib/actions";

export default function CommentForm({ slug }) {
  const [state, formAction, pending] = useActionState(submitCommentAction, {});

  if (state?.success) {
    return (
      <p role="status" className="rounded-md border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-400">
        {state.success}
      </p>
    );
  }

  return (
    <form action={formAction} className="flex flex-col gap-3">
      <input type="hidden" name="slug" value={slug} />

      {state?.error && (
        <p role="alert" className="rounded-md border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">
          {state.error}
        </p>
      )}

      <div className="grid gap-3 sm:grid-cols-2">
        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium">Name</span>
          <input
            name="author_name"
            required
            maxLength={120}
            autoComplete="name"
            className="rounded-md border border-border-subtle bg-background px-3 py-2"
          />
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium">Email (not published)</span>
          <input
            type="email"
            name="author_email"
            required
            maxLength={255}
            autoComplete="email"
            className="rounded-md border border-border-subtle bg-background px-3 py-2"
          />
        </label>
      </div>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium">Website (optional)</span>
        <input
          type="url"
          name="author_url"
          maxLength={2048}
          autoComplete="url"
          className="rounded-md border border-border-subtle bg-background px-3 py-2"
        />
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium">Comment</span>
        <textarea
          name="content"
          required
          minLength={2}
          maxLength={5000}
          rows={5}
          className="rounded-md border border-border-subtle bg-background px-3 py-2"
        />
      </label>

      <button
        type="submit"
        disabled={pending}
        className="self-start rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast disabled:opacity-60"
      >
        {pending ? "Posting…" : "Post comment"}
      </button>
    </form>
  );
}
