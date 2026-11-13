"use client";

import { useActionState } from "react";

import { createTermAction } from "@/lib/actions";

export default function TermForm({ categories }) {
  const [state, formAction, pending] = useActionState(createTermAction, {});

  return (
    <form
      action={formAction}
      className="flex flex-col gap-3 rounded-xl border border-border-subtle bg-surface/40 p-4"
    >
      <h2 className="text-sm font-semibold">Add a term</h2>

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

      <div className="grid gap-3 sm:grid-cols-2">
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Taxonomy</span>
          <select
            name="taxonomy"
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          >
            <option value="category">Category</option>
            <option value="tag">Tag</option>
          </select>
        </label>

        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Name</span>
          <input
            name="name"
            required
            maxLength={200}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          />
        </label>
      </div>

      <label className="flex flex-col gap-1 text-sm">
        <span className="text-xs uppercase tracking-wide text-muted">Description</span>
        <input
          name="description"
          maxLength={2000}
          className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
        />
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="text-xs uppercase tracking-wide text-muted">
          Parent category (optional)
        </span>
        <select
          name="parent_id"
          className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
        >
          <option value="">None</option>
          {categories.map((category) => (
            <option key={category.id} value={category.id}>
              {category.name}
            </option>
          ))}
        </select>
      </label>

      <button
        type="submit"
        disabled={pending}
        className="self-start rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast disabled:opacity-60"
      >
        {pending ? "Saving…" : "Create term"}
      </button>
    </form>
  );
}
