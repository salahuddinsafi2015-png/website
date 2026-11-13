export const instant = false;

import SettingsForm from "@/components/admin/SettingsForm";
import { getSettings } from "@/lib/admin-api";
import { requireToken } from "@/lib/session";

export default async function SettingsPage() {
  const token = await requireToken();

  let payload;

  try {
    payload = await getSettings(token);
  } catch {
    return (
      <p className="rounded-lg border border-border-subtle p-4 text-sm">
        Settings are limited to administrators.
      </p>
    );
  }

  const values = {};

  for (const setting of payload?.data || []) {
    if (setting?.name) {
      values[setting.name] = setting.value;
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Settings</h1>
        <p className="mt-1 text-sm text-muted">
          Saved values are cached and refreshed across the site immediately.
        </p>
      </header>

      <SettingsForm values={values} />
    </div>
  );
}