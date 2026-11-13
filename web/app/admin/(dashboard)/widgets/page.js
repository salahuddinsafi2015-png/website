export const instant = false;

import WidgetManager from "@/components/admin/WidgetManager";
import { getMenus, getWidgets } from "@/lib/admin-api";
import { requireToken } from "@/lib/session";

export default async function WidgetsPage() {
  const token = await requireToken();

  const [widgetsPayload, menusPayload] = await Promise.all([
    getWidgets(token),
    getMenus(token),
  ]);

  const widgets = widgetsPayload?.data || [];
  const areas = widgetsPayload?.meta?.areas || {};
  const types = widgetsPayload?.meta?.types || {};
  const menus = menusPayload?.data || [];

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Widgets</h1>
        <p className="mt-1 text-sm text-muted">
          Widgets render in the theme&rsquo;s areas: the footer of every page and the
          sidebar of articles.
        </p>
      </header>

      <WidgetManager widgets={widgets} menus={menus} areas={areas} types={types} />
    </div>
  );
}