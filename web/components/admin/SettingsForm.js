"use client";

import { useActionState } from "react";

import { saveSettingsAction } from "@/lib/actions";

export default function SettingsForm({ values }) {
  const [state, formAction, pending] = useActionState(saveSettingsAction, {});

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

      <fieldset className="rounded-xl border border-border-subtle bg-surface/40 p-4">
        <legend className="px-1 text-sm font-semibold">General</legend>
        <div className="mt-3 grid gap-4 sm:grid-cols-2">
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">Site name</span>
            <input
              name="site.name"
              defaultValue={values["site.name"] || ""}
              className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
            />
          </label>
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">Tagline</span>
            <input
              name="site.tagline"
              defaultValue={values["site.tagline"] || ""}
              className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
            />
          </label>
          <label className="flex flex-col gap-1 text-sm sm:col-span-2">
            <span className="text-xs uppercase tracking-wide text-muted">Description</span>
            <textarea
              name="site.description"
              rows={3}
              defaultValue={values["site.description"] || ""}
              className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
            />
          </label>
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">Posts per page</span>
            <input
              type="number"
              name="site.posts_per_page"
              min={1}
              max={50}
              defaultValue={values["site.posts_per_page"] ?? 9}
              className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
            />
          </label>
        </div>
      </fieldset>

      <fieldset className="rounded-xl border border-border-subtle bg-surface/40 p-4">
        <legend className="px-1 text-sm font-semibold">SEO defaults</legend>
        <div className="mt-3 grid gap-4 sm:grid-cols-2">
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">Title suffix</span>
            <input
              name="seo.default_title_suffix"
              defaultValue={values["seo.default_title_suffix"] || ""}
              className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
            />
          </label>
          <label className="flex flex-col gap-1 text-sm sm:col-span-2">
            <span className="text-xs uppercase tracking-wide text-muted">
              Default meta description
            </span>
            <textarea
              name="seo.default_description"
              rows={2}
              defaultValue={values["seo.default_description"] || ""}
              className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
            />
          </label>
        </div>
      </fieldset>

      <fieldset className="rounded-xl border border-border-subtle bg-surface/40 p-4">
        <legend className="px-1 text-sm font-semibold">Discussion &amp; access</legend>
        <div className="mt-3 flex flex-col gap-2 text-sm">
          <label className="flex items-center gap-2">
            <input
              type="checkbox"
              name="comments.require_moderation"
              defaultChecked={Boolean(values["comments.require_moderation"])}
            />
            Hold new comments for moderation
          </label>
          <label className="flex items-center gap-2">
            <input
              type="checkbox"
              name="users.allow_registration"
              defaultChecked={Boolean(values["users.allow_registration"])}
            />
            Allow public registration
          </label>
        </div>
      </fieldset>

      <fieldset
        id="integrations"
        className="rounded-xl border border-border-subtle bg-surface/40 p-4"
      >
        <legend className="px-1 text-sm font-semibold">Integrations</legend>
        <p className="mt-2 text-xs text-muted">
          The measurement and AdSense client IDs are public and load on the site.
          The property, account and service account key stay private to administrators.
          See the Guides on the Insights page for the exact Google setup steps.
        </p>
        <div className="mt-3 grid gap-4 sm:grid-cols-2">
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">
              GA4 measurement ID
            </span>
            <input
              name="analytics.measurement_id"
              defaultValue={values["analytics.measurement_id"] || ""}
              placeholder="G-XXXXXXXXXX"
              className="rounded-md border border-border-subtle bg-background px-3 py-1.5 font-mono text-sm"
            />
            <span className="text-xs text-muted">Added to public pages as gtag.js.</span>
          </label>
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">
              GA4 property ID
            </span>
            <input
              name="analytics.property_id"
              defaultValue={values["analytics.property_id"] || ""}
              placeholder="123456789"
              className="rounded-md border border-border-subtle bg-background px-3 py-1.5 font-mono text-sm"
            />
            <span className="text-xs text-muted">Used by the dashboard reporting.</span>
          </label>
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">
              AdSense client ID
            </span>
            <input
              name="adsense.client_id"
              defaultValue={values["adsense.client_id"] || ""}
              placeholder="ca-pub-0000000000000000"
              className="rounded-md border border-border-subtle bg-background px-3 py-1.5 font-mono text-sm"
            />
            <span className="text-xs text-muted">Loads the AdSense script on public pages.</span>
          </label>
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">
              AdSense account
            </span>
            <input
              name="adsense.account"
              defaultValue={values["adsense.account"] || ""}
              placeholder="accounts/pub-0000000000000000"
              className="rounded-md border border-border-subtle bg-background px-3 py-1.5 font-mono text-sm"
            />
            <span className="text-xs text-muted">Used for the earnings dashboard.</span>
          </label>
          <label className="flex flex-col gap-1 text-sm sm:col-span-2">
            <span className="text-xs uppercase tracking-wide text-muted">
              Google service account key (JSON)
            </span>
            <textarea
              name="analytics.credentials_json"
              rows={4}
              defaultValue={values["analytics.credentials_json"] || ""}
              placeholder='{"type":"service_account","client_email":"…","private_key":"…"}'
              className="rounded-md border border-border-subtle bg-background px-3 py-1.5 font-mono text-xs"
            />
            <span className="text-xs text-muted">
              Stored server-side only and never sent to browsers or visitors.
            </span>
          </label>
        </div>
      </fieldset>

      <button
        type="submit"
        disabled={pending}
        className="self-start rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast disabled:opacity-60"
      >
        {pending ? "Saving…" : "Save settings"}
      </button>
    </form>
  );
}
