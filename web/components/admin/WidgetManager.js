"use client";

import { useActionState, useMemo, useOptimistic, useState, useTransition } from "react";
import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
} from "@dnd-kit/core";
import {
  SortableContext,
  arrayMove,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { useRouter } from "next/navigation";

import { deleteWidgetAction, reorderWidgetsAction, saveWidgetAction } from "@/lib/actions";
import { createSortableA11y, HelpNote } from "@/lib/dnd-a11y";

function WidgetFields({ widget, menus, areas, types }) {
  const [state, formAction, pending] = useActionState(saveWidgetAction, {});
  const [type, setType] = useState(widget?.type || "rich_text");

  return (
    <form action={formAction} className="flex flex-col gap-3">
      {widget && <input type="hidden" name="id" value={widget.id} />}

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

      <div className="grid gap-3 sm:grid-cols-2">
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Area</span>
          <select
            name="area"
            defaultValue={widget?.area || "sidebar"}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          >
            {Object.entries(areas || {}).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </label>

        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Type</span>
          <select
            name="type"
            value={type}
            onChange={(event) => setType(event.target.value)}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          >
            {Object.entries(types || {}).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </label>
      </div>

      <label className="flex flex-col gap-1 text-sm">
        <span className="text-xs uppercase tracking-wide text-muted">Title</span>
        <input
          name="title"
          maxLength={160}
          defaultValue={widget?.title || ""}
          className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
        />
      </label>

      {type === "rich_text" && (
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Content (HTML)</span>
          <textarea
            name="content"
            rows={4}
            defaultValue={widget?.text || ""}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5 font-mono text-sm"
            placeholder="<p>About this site…</p>"
          />
          <span className="text-xs text-muted">Sanitised on save.</span>
        </label>
      )}

      {type === "menu" && (
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Menu</span>
          <select
            name="menu_id"
            required
            defaultValue={widget?.menu_id || ""}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          >
            <option value="">Choose a menu…</option>
            {menus.map((menu) => (
              <option key={menu.id} value={menu.id}>
                {menu.name}
              </option>
            ))}
          </select>
        </label>
      )}

      <div className="grid gap-3 sm:grid-cols-2">
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Order</span>
          <input
            type="number"
            name="sort_order"
            min={0}
            defaultValue={widget?.sort_order ?? 0}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          />
        </label>

        <label className="flex items-center gap-2 self-end text-sm">
          <input
            type="checkbox"
            name="is_active"
            defaultChecked={widget ? widget.is_active : true}
          />
          Visible
        </label>
      </div>

      <button
        type="submit"
        disabled={pending}
        className="self-start rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast disabled:opacity-60"
      >
        {pending ? "Saving…" : widget ? "Save widget" : "Create widget"}
      </button>
    </form>
  );
}

function WidgetCard({ widget, menus, areas, types }) {
  const {
    attributes,
    listeners,
    setNodeRef,
    setActivatorNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: widget.id, data: { area: widget.area } });

  return (
    <div
      ref={setNodeRef}
      style={{
        transform: CSS.Translate.toString(transform),
        transition,
      }}
      className={`rounded-xl border border-border-subtle p-4 ${
        isDragging ? "z-10 bg-surface shadow-lg" : ""
      }`}
    >
      <details>
        <summary className="flex cursor-pointer flex-wrap items-center justify-between gap-2 text-sm">
          <span className="flex min-w-0 items-center gap-2 font-medium">
            <button
              type="button"
              ref={setActivatorNodeRef}
              aria-label={`Drag to reorder ${widget.title || "widget"}`}
              className="cursor-grab touch-none select-none rounded text-muted ring-offset-2 transition hover:text-foreground focus-visible:ring-2 focus-visible:ring-accent"
              {...attributes}
              {...listeners}
            >
              ≡
            </button>
            <span className="truncate">{widget.title || "(untitled)"}</span>
          </span>
          <span className="text-xs text-muted">
            {widget.type} · #{widget.sort_order}
            {widget.is_active ? "" : " · hidden"}
          </span>
        </summary>

        <div className="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_8rem]">
          <WidgetFields widget={widget} menus={menus} areas={areas} types={types} />
          <form action={deleteWidgetAction}>
            <input type="hidden" name="id" value={widget.id} />
            <button
              type="submit"
              className="rounded border border-red-500/40 px-2 py-1 text-xs text-red-600 hover:bg-red-500/10 dark:text-red-400"
            >
              Delete
            </button>
          </form>
        </div>
      </details>
    </div>
  );
}

/**
 * Widgets sort inside their own area; moving a widget to another area stays
 * in its edit form so a drag and an area change never race each other.
 *
 * The ordered list is optimistic: dnd-kit keeps the new order visible while
 * the server action runs, and the state rolls back automatically if it fails.
 */
export default function WidgetManager({ widgets: propsWidgets, menus, areas, types }) {
  const router = useRouter();

  const [widgets, applyOptimistic] = useOptimistic(propsWidgets, (current, applied) => applied);
  const [savingOrder, startReorder] = useTransition();
  const [orderError, setOrderError] = useState("");

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const a11y = useMemo(
    () =>
      createSortableA11y(
        "widget",
        new Map(widgets.map((widget) => [widget.id, widget.title || `untitled #${widget.id}`])),
      ),
    [widgets],
  );

  const sections = useMemo(() => {
    const registry = Object.entries(areas || {}).map(([area, label]) => ({
      area,
      label,
    }));

    const extra = [...new Set(widgets.map((widget) => widget.area))]
      .filter((area) => !(area in (areas || {})))
      .map((area) => ({ area, label: null }));

    const names = [...registry, ...extra];

    return names.map(({ area, label }) => ({
      area,
      label: label || area,
      widgetList: widgets
        .filter((widget) => widget.area === area)
        .sort((a, b) => a.sort_order - b.sort_order || a.id - b.id),
    }));
  }, [areas, widgets]);

  function applyAreaOrder(area, ordered) {
    if (!ordered.length) {
      return;
    }

    const touched = new Set(ordered.map((widget) => widget.id));
    const assigned = ordered.map((widget, index) => ({
      ...widget,
      sort_order: index,
    }));
    const merged = [
      ...propsWidgets.filter((widget) => !touched.has(widget.id)),
      ...assigned,
    ];

    setOrderError("");
    startReorder(async () => {
      applyOptimistic(merged);

      try {
        await reorderWidgetsAction(assigned.map(({ id, sort_order }) => ({ id, sort_order })));
      } catch (error) {
        setOrderError(error?.message || "The new order could not be saved.");
        router.refresh();
      }
    });
  }

  function handleDragEnd(event) {
    const { active, over } = event;

    if (!over || active.id === over.id) {
      return;
    }

    if (active.data.current?.area !== over.data.current?.area) {
      return;
    }

    const area = active.data.current.area;
    const list = widgets
      .filter((widget) => widget.area === area)
      .sort((a, b) => a.sort_order - b.sort_order || a.id - b.id);

    const from = list.findIndex((widget) => widget.id === active.id);
    const to = list.findIndex((widget) => widget.id === over.id);

    if (from === -1 || to === -1) {
      return;
    }

    applyAreaOrder(area, arrayMove(list, from, to));
  }

  return (
    <div className="flex flex-col gap-6">
      <section className="rounded-xl border border-border-subtle bg-surface/40 p-4">
        <h2 className="text-sm font-semibold">Add a widget</h2>
        <div className="mt-3">
          <WidgetFields menus={menus} areas={areas} types={types} />
        </div>
      </section>

      <div className="flex flex-col gap-6">
        <p aria-live="polite" className="text-xs text-muted">
          {savingOrder ? (
            <span className="text-accent">Saving order…</span>
          ) : orderError ? (
            <span role="alert" className="text-red-600 dark:text-red-400">
              {orderError}
            </span>
          ) : (
            <span>
              Reorder with the pointer or the keyboard; drops save instantly.
            </span>
          )}
        </p>

        <HelpNote
          noun="widget"
          nesting="A widget’s area is changed with its “Area” select — widgets sort, but stay in their area."
        />

        <DndContext
          sensors={sensors}
          accessibility={a11y}
          collisionDetection={closestCenter}
          onDragEnd={handleDragEnd}
        >
          {sections.map(({ area, label, widgetList }) => (
            <section key={area} className="flex flex-col gap-3">
              <h2 className="text-sm font-semibold">
                {label} ({widgetList.length})
              </h2>

              <SortableContext
                items={widgetList.map((widget) => widget.id)}
                strategy={verticalListSortingStrategy}
              >
                <div className="flex flex-col gap-3">
                  {widgetList.map((widget) => (
                    <WidgetCard
                      key={widget.id}
                      widget={widget}
                      menus={menus}
                      areas={areas}
                      types={types}
                    />
                  ))}
                </div>
              </SortableContext>

              {!widgetList.length && (
                <p className="rounded-lg border border-dashed border-border-subtle p-6 text-center text-sm text-muted">
                  Nothing here yet.
                </p>
              )}
            </section>
          ))}
        </DndContext>
      </div>
    </div>
  );
}
