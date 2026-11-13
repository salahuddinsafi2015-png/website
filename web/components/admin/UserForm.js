"use client";

import { useActionState } from "react";

import { createUserAction } from "@/lib/actions";

export default function UserForm() {
  const [state, formAction, pending] = useActionState(createUserAction, {});

  return (
    <form
      action={formAction}
      className="flex flex-col gap-3 rounded-xl border border-border-subtle bg-surface/40 p-4"
    >
      <h2 className="text-sm font-semibold">Invite a teammate</h2>

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
          <span className="text-xs uppercase tracking-wide text-muted">Name</span>
          <input
            name="name"
            required
            maxLength={255}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          />
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Email</span>
          <input
            type="email"
            name="email"
            autoComplete="email"
            required
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          />
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Password</span>
          <input
            type="password"
            name="password"
            autoComplete="new-password"
            required
            minLength={10}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          />
          <span className="text-xs text-muted">At least 10 characters with letters and numbers.</span>
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Role</span>
          <select
            name="role"
            defaultValue="author"
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          >
            <option value="administrator">Administrator</option>
            <option value="editor">Editor</option>
            <option value="author">Author</option>
            <option value="contributor">Contributor</option>
            <option value="subscriber">Subscriber</option>
          </select>
        </label>
      </div>

      <button
        type="submit"
        disabled={pending}
        className="self-start rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast disabled:opacity-60"
      >
        {pending ? "Creating…" : "Create user"}
      </button>
    </form>
  );
}
