export const instant = false;

import Image from "next/image";

import MediaForm from "@/components/admin/MediaForm";
import { getMedia } from "@/lib/admin-api";
import { deleteMediaAction } from "@/lib/actions";
import { requireToken } from "@/lib/session";

export default async function MediaPage({ searchParams }) {
  const token = await requireToken();
  const params = await searchParams;
  const search = typeof params?.search === "string" ? params.search : "";

  const payload = await getMedia(token, { search: search || undefined }).catch(() => null);
  const items = payload?.data || [];

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Media library</h1>
        <p className="mt-1 text-sm text-muted">
          Uploads are validated against an allow-list and served from the public disk.
        </p>
      </header>

      <MediaForm />

      <form className="flex gap-2">
        <label htmlFor="media-search" className="sr-only">
          Search media
        </label>
        <input
          id="media-search"
          name="search"
          defaultValue={search}
          placeholder="Search by filename"
          className="flex-1 rounded-md border border-border-subtle bg-background px-3 py-1.5 text-sm"
        />
        <button
          type="submit"
          className="rounded-md border border-border-subtle px-4 py-1.5 text-sm hover:border-accent"
        >
          Search
        </button>
      </form>

      {items.length ? (
        <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          {items.map((item) => (
            <li key={item.id} className="overflow-hidden rounded-xl border border-border-subtle">
              {item.is_image ? (
                <Image
                  src={item.url}
                  alt={item.alt_text || item.filename}
                  width={item.width || 400}
                  height={item.height || 300}
                  className="h-32 w-full object-cover"
                  sizes="(max-width: 640px) 50vw, 25vw"
                />
              ) : (
                <div className="flex h-32 items-center justify-center bg-surface text-xs uppercase tracking-wide text-muted">
                  {item.mime_type.split("/").pop()}
                </div>
              )}
              <div className="flex flex-col gap-1 p-3 text-xs">
                <p className="truncate font-medium" title={item.filename}>
                  {item.filename}
                </p>
                <p className="text-muted">{Math.round(item.size / 1024)} KB</p>
                <form action={deleteMediaAction} className="mt-1">
                  <input type="hidden" name="id" value={item.id} />
                  <button
                    type="submit"
                    className="rounded border border-red-500/40 px-2 py-1 text-red-600 hover:bg-red-500/10 dark:text-red-400"
                  >
                    Delete
                  </button>
                </form>
              </div>
            </li>
          ))}
        </ul>
      ) : (
        <p className="rounded-lg border border-dashed border-border-subtle p-8 text-center text-sm text-muted">
          No media uploaded yet.
        </p>
      )}
    </div>
  );
}