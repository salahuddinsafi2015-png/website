"use client";

import { useActionState, useState } from "react";

import { saveThemeAction } from "@/lib/actions";

export default function ThemeForm({ themes, active, accent }) {
  const [state, formAction, pending] = useActionState(saveThemeAction, {});
  const [selected, setSelected] = useState(active || themes[0]?.key);
  const current = themes.find((theme) => theme.key === selected);

  return (
    <form action={formAction} className="flex flex-col gap-5">
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

      <fieldset>
        <legend className="text-sm font-semibold">Theme</legend>
        <div className="mt-3 grid gap-3 sm:grid-cols-3">
          {themes.map((theme) => {
            const isSelected = selected === theme.key;

            return (
              <label
                key={theme.key}
                className={`flex cursor-pointer flex-col gap-2 rounded-xl border p-4 transition ${
                  isSelected
                    ? "border-accent ring-1 ring-accent"
                    : "border-border-subtle hover:border-accent/60"
                }`}
              >
                <input
                  type="radio"
                  name="theme"
                  value={theme.key}
                  checked={isSelected}
                  onChange={() => setSelected(theme.key)}
                  className="sr-only"
                />
                <span className="flex items-center justify-between text-sm font-semibold">
                  {theme.label}
                  {isSelected && <span className="text-xs text-accent">Active</span>}
                </span>
                <span className="text-xs text-muted">{theme.description}</span>
                <span
                  aria-hidden="true"
                  className="mt-1 h-8 w-full rounded-md border border-black/10"
                  style={{ backgroundColor: theme.default_accent || "#1d4ed8" }}
                />
              </label>
            );
          })}
        </div>
      </fieldset>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium">Accent colour</span>
        <div className="flex items-center gap-3">
          <input
            type="color"
            name="accent"
            defaultValue={accent || current?.default_accent || "#1d4ed8"}
            className="h-10 w-16 cursor-pointer rounded-md border border-border-subtle bg-background"
          />
          <span className="text-xs text-muted">
            Overrides the theme accent. The theme default is used when this is cleared.
          </span>
        </div>
      </label>

      <button
        type="submit"
        disabled={pending}
        className="self-start rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast disabled:opacity-60"
      >
        {pending ? "Saving…" : "Save appearance"}
      </button>
    </form>
  );
}
