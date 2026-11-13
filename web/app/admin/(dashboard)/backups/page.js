export const instant = false;

import BackupManager from "@/components/admin/BackupManager";
import { getBackups } from "@/lib/admin-api";
import { requireToken } from "@/lib/session";

export const metadata = {
  title: "Backups",
  robots: { index: false, follow: false },
};

export default async function BackupsPage() {
  const token = await requireToken();

  let payload;

  try {
    payload = await getBackups(token);
  } catch {
    return (
      <p className="rounded-lg border border-border-subtle p-4 text-sm">
        Backups are limited to administrators.
      </p>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Backups</h1>
        <p className="mt-1 text-sm text-muted">
          Snapshot the database, the media library, or both. Restoring first saves a
          safety snapshot of the current state, so a restore can itself be undone.
        </p>
      </header>

      <BackupManager
        backups={payload?.data || []}
        meta={payload?.meta || {}}
      />
    </div>
  );
}
