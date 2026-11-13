export const instant = false;

import Link from "next/link";
import { notFound } from "next/navigation";

import EditorForm from "@/components/admin/EditorForm";
import RevisionHistory from "@/components/admin/RevisionHistory";
import { getPost, getPostRevisions, getTerms } from "@/lib/admin-api";
import { requireToken } from "@/lib/session";

export default async function EditPostPage({ params }) {
  const token = await requireToken();
  const { id } = await params;

  let post;

  try {
    post = await getPost(token, id);
  } catch {
    notFound();
  }

  const [categories, tags, history] = await Promise.all([
    getTerms(token, { taxonomy: "category" }).catch(() => ({ data: [] })),
    getTerms(token, { taxonomy: "tag" }).catch(() => ({ data: [] })),
    getPostRevisions(token, id, { per_page: 10 }).catch(() => ({ data: [] })),
  ]);

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Edit post</h1>
          <p className="mt-1 text-sm text-muted">
            Status: <span className="capitalize">{post.status}</span>
            {post.published_at && (
              <>
                {" · "}
                <time dateTime={post.published_at}>
                  {new Date(post.published_at).toLocaleString("en-US")}
                </time>
              </>
            )}
          </p>
        </div>
        <Link
          href={post.path}
          className="rounded-md border border-border-subtle px-3 py-1.5 text-sm hover:border-accent"
        >
          Preview
        </Link>
      </header>

      <EditorForm post={post} categories={categories?.data || []} tags={tags?.data || []} />

      <RevisionHistory postId={post.id} revisions={history?.data || []} />
    </div>
  );
}