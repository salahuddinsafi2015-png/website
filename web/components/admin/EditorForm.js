"use client";

import Link from "next/link";
import { useActionState } from "react";

import BlockEditor from "@/components/admin/BlockEditor";
import { savePostAction } from "@/lib/actions";

const STATUSES = [
  ["draft", "Draft"],
  ["pending", "Pending review"],
  ["publish", "Published"],
  ["future", "Scheduled"],
  ["private", "Private"],
];

function toLocalInput(value) {
  if (!value) {
    return "";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return "";
  }

  const offset = date.getTimezoneOffset();

  return new Date(date.getTime() - offset * 60 * 1000).toISOString().slice(0, 16);
}

export default function EditorForm({ post, categories, tags }) {
  const [state, formAction, pending] = useActionState(savePostAction, {});
  const selectedTerms = new Set(
    [...(post?.categories || []), ...(post?.tags || [])].map((term) => term.id),
  );

  return (
    <form action={formAction} className="flex flex-col gap-5">
      {post?.id && <input type="hidden" name="id" value={post.id} />}

      {state?.error && (
        <p
          role="alert"
          className="rounded-md border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400"
        >
          {state.error}
        </p>
      )}

      <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div className="flex flex-col gap-4">
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium">Title</span>
            <input
              name="title"
              defaultValue={post?.title || ""}
              maxLength={255}
              className="rounded-md border border-border-subtle bg-background px-3 py-2 text-lg"
              placeholder="A clear, descriptive headline"
            />
          </label>

          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium">Slug</span>
            <input
              name="slug"
              defaultValue={post?.slug || ""}
              maxLength={200}
              className="rounded-md border border-border-subtle bg-background px-3 py-2 font-mono text-sm"
              placeholder="generated from the title when left blank"
            />
          </label>

          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium">Excerpt</span>
            <textarea
              name="excerpt"
              defaultValue={post?.excerpt || ""}
              rows={2}
              maxLength={1000}
              className="rounded-md border border-border-subtle bg-background px-3 py-2"
              placeholder="Shown on archive pages. Generated from the content when blank."
            />
          </label>

          <BlockEditor initialBlocks={post?.blocks || null} initialContent={post?.content || ""} />
        </div>

        <aside className="flex flex-col gap-4">
          <div className="rounded-xl border border-border-subtle bg-surface/40 p-4">
            <h2 className="text-sm font-semibold">Publishing</h2>

            <label className="mt-3 flex flex-col gap-1 text-sm">
              <span className="text-xs uppercase tracking-wide text-muted">Status</span>
              <select
                name="status"
                defaultValue={post?.status || "draft"}
                className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
              >
                {STATUSES.map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </label>

            <label className="mt-3 flex flex-col gap-1 text-sm">
              <span className="text-xs uppercase tracking-wide text-muted">Publish date</span>
              <input
                type="datetime-local"
                name="published_at"
                defaultValue={toLocalInput(post?.published_at)}
                className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
              />
              <span className="text-xs text-muted">Required for scheduled posts.</span>
            </label>

            <label className="mt-3 flex flex-col gap-1 text-sm">
              <span className="text-xs uppercase tracking-wide text-muted">Type</span>
              <select
                name="type"
                defaultValue={post?.type || "post"}
                className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
              >
                <option value="post">Post</option>
                <option value="page">Page</option>
              </select>
            </label>

            <label className="mt-3 flex flex-col gap-1 text-sm">
              <span className="text-xs uppercase tracking-wide text-muted">Comments</span>
              <select
                name="comment_status"
                defaultValue={post?.comment_status || "open"}
                className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
              >
                <option value="open">Open</option>
                <option value="closed">Closed</option>
              </select>
            </label>

            <div className="mt-4 flex flex-col gap-2">
              <button
                type="submit"
                disabled={pending}
                className="rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast disabled:opacity-60"
              >
                {pending ? "Saving…" : post?.id ? "Update post" : "Create post"}
              </button>
              <Link
                href="/admin/posts"
                className="rounded-md border border-border-subtle px-4 py-2 text-center text-sm hover:border-accent"
              >
                Cancel
              </Link>
            </div>
          </div>

          {categories.length > 0 && (
            <fieldset className="rounded-xl border border-border-subtle bg-surface/40 p-4">
              <legend className="px-1 text-sm font-semibold">Categories</legend>
              <div className="mt-2 flex max-h-48 flex-col gap-1 overflow-y-auto text-sm">
                {categories.map((category) => (
                  <label key={category.id} className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      name="term_ids"
                      value={category.id}
                      defaultChecked={selectedTerms.has(category.id)}
                    />
                    {category.name}
                  </label>
                ))}
              </div>
            </fieldset>
          )}

          {tags.length > 0 && (
            <fieldset className="rounded-xl border border-border-subtle bg-surface/40 p-4">
              <legend className="px-1 text-sm font-semibold">Tags</legend>
              <div className="mt-2 flex max-h-48 flex-col gap-1 overflow-y-auto text-sm">
                {tags.map((tag) => (
                  <label key={tag.id} className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      name="term_ids"
                      value={tag.id}
                      defaultChecked={selectedTerms.has(tag.id)}
                    />
                    {tag.name}
                  </label>
                ))}
              </div>
            </fieldset>
          )}

          {post?.id && (
            <p className="text-xs text-muted">
              Every save stores a revision, so earlier versions can be recovered from the API.
            </p>
          )}
        </aside>
      </div>
    </form>
  );
}
