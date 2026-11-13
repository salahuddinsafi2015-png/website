"use client";

import { useActionState } from "react";

import { createMenuAction, deleteMenuAction, updateMenuAction } from "@/lib/actions";

export default function MenuForm({ menu, locations }) {
  const action = menu ? updateMenuAction : createMenuAction;
  const [state, formAction, pending] = useActionState(action, {});

  return (
    <div className="flex flex-col gap-3">
      <form
        action={formAction}
        className="flex flex-col gap-3 rounded-xl border border-border-subtle bg-surface/40 p-4"
      >
        <h2 className="text-sm font-semibold">
          {menu ? "Menu settings" : "Create a menu"}
        </h2>

        {menu && <input type="hidden" name="id" value={menu.id} />}

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
          <span className="text-xs uppercase tracking-wide text-muted">Name</span>
          <input
            name="name"
            required
            maxLength={120}
            defaultValue={menu?.name || ""}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          />
        </label>

        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Location</span>
          <select
            name="location"
            defaultValue={menu?.location || ""}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          >
            <option value="">Not assigned</option>
            {Object.entries(locations || {}).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
          <span className="text-xs text-muted">
            A location can hold one menu. Assigning it here replaces any other menu.
          </span>
        </label>

        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Description</span>
          <input
            name="description"
            maxLength={255}
            defaultValue={menu?.description || ""}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          />
        </label>

        <button
          type="submit"
          disabled={pending}
          className="self-start rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast disabled:opacity-60"
        >
          {pending ? "Saving…" : menu ? "Save menu" : "Create menu"}
        </button>
      </form>

      {menu && (
        <form action={deleteMenuAction}>
          <input type="hidden" name="id" value={menu.id} />
          <button
            type="submit"
            className="rounded-md border border-red-500/40 px-3 py-1.5 text-xs text-red-600 hover:bg-red-500/10 dark:text-red-400"
          >
            Delete this menu
          </button>
        </form>
      )}
    </div>
  );
}
