export const instant = false;

import EditorForm from "@/components/admin/EditorForm";
import { getTerms } from "@/lib/admin-api";
import { requireToken } from "@/lib/session";

export default async function NewPostPage() {
  const token = await requireToken();

  const [categories, tags] = await Promise.all([
    getTerms(token, { taxonomy: "category" }).catch(() => ({ data: [] })),
    getTerms(token, { taxonomy: "tag" }).catch(() => ({ data: [] })),
  ]);

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">New post</h1>
        <p className="mt-1 text-sm text-muted">Drafts stay private until you publish them.</p>
      </header>

      <EditorForm post={null} categories={categories?.data || []} tags={tags?.data || []} />
    </div>
  );
}