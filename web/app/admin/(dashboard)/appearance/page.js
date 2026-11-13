export const instant = false;

import ThemeForm from "@/components/admin/ThemeForm";
import { getThemes } from "@/lib/admin-api";
import { requireToken } from "@/lib/session";

export default async function AppearancePage() {
  const token = await requireToken();

  let data;

  try {
    data = await getThemes(token);
  } catch {
    return (
      <p className="rounded-lg border border-border-subtle p-4 text-sm">
        Appearance settings are limited to administrators.
      </p>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Appearance</h1>
        <p className="mt-1 text-sm text-muted">
          The chosen theme and accent are applied to the public site immediately.
        </p>
      </header>

      <ThemeForm themes={data?.themes || []} active={data?.active} accent={data?.accent} />

      <p className="text-xs text-muted">
        Themes are registered in <code>config/cms.php</code>. Adding a palette there
        makes it selectable here without a database change.
      </p>
    </div>
  );
}