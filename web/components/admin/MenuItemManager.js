"use client";

import { useActionState, useMemo, useOptimistic, useState, useTransition } from "react";
import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useDroppable,
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

import { deleteMenuItemAction, reorderMenuItemsAction, saveMenuItemAction } from "@/lib/actions";
import { createSortableA11y, HelpNote } from "@/lib/dnd-a11y";

const TYPES = [
  ["custom", "Custom link"],
  ["post", "Post"],
  ["page", "Page"],
  ["category", "Category"],
];

function LinkTarget({ type, item, posts, pages, categories }) {
  if (type === "custom") {
    return (
      <label className="flex flex-col gap-1 text-sm">
        <span className="text-xs uppercase tracking-wide text-muted">URL</span>
        <input
          name="url"
          required
          maxLength={2048}
          defaultValue={item?.type === "custom" ? item.url || "" : ""}
          placeholder="/posts or https://example.com"
          className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
        />
      </label>
    );
  }

  const options =
    type === "post" ? posts : type === "page" ? pages : categories;

  return (
    <label className="flex flex-col gap-1 text-sm">
      <span className="text-xs uppercase tracking-wide text-muted">Links to</span>
      <select
        name="linkable_id"
        required
        defaultValue={item?.type === type ? item.linkable_id || "" : ""}
        className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
      >
        <option value="">Choose…</option>
        {options.map((option) => (
          <option key={option.id} value={option.id}>
            {option.title || option.name}
          </option>
        ))}
      </select>
    </label>
  );
}

function ItemForm({ menu, item, posts, pages, categories }) {
  const [state, formAction, pending] = useActionState(saveMenuItemAction, {});
  const [type, setType] = useState(item?.type || "custom");
  const parents = (menu.items || []).filter((candidate) => candidate.id !== item?.id);

  return (
    <form action={formAction} className="flex flex-col gap-3">
      <input type="hidden" name="menu_id" value={menu.id} />
      {item && <input type="hidden" name="item_id" value={item.id} />}

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
          <span className="text-xs uppercase tracking-wide text-muted">Label</span>
          <input
            name="label"
            required
            maxLength={160}
            defaultValue={item?.label || ""}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          />
        </label>

        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Type</span>
          <select
            name="type"
            value={type}
            onChange={(event) => setType(event.target.value)}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          >
            {TYPES.map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </label>
      </div>

      <LinkTarget
        type={type}
        item={item}
        posts={posts}
        pages={pages}
        categories={categories}
      />

      <div className="grid gap-3 sm:grid-cols-2">
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Order</span>
          <input
            type="number"
            name="sort_order"
            min={0}
            defaultValue={item?.sort_order ?? 0}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          />
        </label>

        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Opens in</span>
          <select
            name="target"
            defaultValue={item?.target || "_self"}
            className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
          >
            <option value="_self">Same tab</option>
            <option value="_blank">New tab</option>
          </select>
        </label>
      </div>

      <label className="flex flex-col gap-1 text-sm">
        <span className="text-xs uppercase tracking-wide text-muted">Nested under</span>
        <select
          name="parent_id"
          defaultValue={item?.parent_id || ""}
          className="rounded-md border border-border-subtle bg-background px-3 py-1.5"
        >
          <option value="">Top level</option>
          {parents.map((candidate) => (
            <option key={candidate.id} value={candidate.id}>
              {candidate.label}
            </option>
          ))}
        </select>
      </label>

      <label className="flex items-center gap-2 text-sm">
        <input
          type="checkbox"
          name="is_active"
          defaultChecked={item ? item.is_active : true}
        />
        Visible on the site
      </label>

      <button
        type="submit"
        disabled={pending}
        className="self-start rounded-md bg-accent px-4 py-2 text-sm font-medium text-accent-contrast disabled:opacity-60"
      >
        {pending ? "Saving…" : item ? "Save item" : "Add item"}
      </button>
    </form>
  );
}

function ItemRow({ item, menu, posts, pages, categories }) {
  const {
    attributes,
    listeners,
    setNodeRef,
    setActivatorNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: item.id, data: { parentId: item.parent_id || null } });

  const resolved =
    item.type === "custom" ? item.url : item.resolved_url || item.url || "";

  return (
    <details
      ref={setNodeRef}
      style={{
        transform: CSS.Translate.toString(transform),
        transition,
      }}
      className={`rounded-lg border border-border-subtle px-3 py-2 ${
        isDragging ? "z-10 bg-surface shadow-lg" : ""
      }`}
    >
      <summary className="flex cursor-pointer flex-wrap items-center justify-between gap-2 text-sm">
        <span className="flex min-w-0 items-center gap-2 font-medium">
          <button
            type="button"
            ref={setActivatorNodeRef}
            aria-label={`Drag to reorder ${item.label}`}
            className="cursor-grab touch-none select-none rounded text-muted ring-offset-2 transition hover:text-foreground focus-visible:ring-2 focus-visible:ring-accent"
            {...attributes}
            {...listeners}
          >
            ≡
          </button>
          <span className="truncate">{item.label}</span>
        </span>
        <span className="text-xs text-muted">
          {item.type}
          {resolved ? ` → ${resolved}` : ""}
          {item.is_active ? "" : " · hidden"}
        </span>
      </summary>

      <div className="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_8rem]">
        <ItemForm
          menu={menu}
          item={item}
          posts={posts}
          pages={pages}
          categories={categories}
        />
        <form action={deleteMenuItemAction}>
          <input type="hidden" name="id" value={item.id} />
          <button
            type="submit"
            className="rounded border border-red-500/40 px-2 py-1 text-xs text-red-600 hover:bg-red-500/10 dark:text-red-400"
          >
            Remove
          </button>
        </form>
      </div>
    </details>
  );
}

/**
 * Every sibling group is also a drop target, so dragging an item onto
 * another group re-parents it; the server rejects impossible nestings
 * (cross-menu parents, self-parents and cycles) with a 422.
 */
function GroupContainer({ parentId, depth, children }) {
  const { isOver, setNodeRef } = useDroppable({
    id: `group:${parentId ?? "root"}`,
    data: { parentId: parentId ?? null, container: true },
  });

  return (
    <ul
      ref={setNodeRef}
      className={`flex min-h-[0.6rem] flex-col gap-1 rounded-md ${
        depth ? "ml-3 border-l border-border-subtle pl-3" : ""
      } ${isOver ? "ring-2 ring-accent/60 ring-offset-2" : ""}`}
    >
      {children}
    </ul>
  );
}

/**
 * The ordered tree is optimistic: dnd-kit keeps the result of a drag visible
 * while the server action runs, and the state rolls back automatically if it
 * fails. One drag maps to one server payload covering only the groups that
 * changed: the source group (renumbered after the item leaves) and the target
 * group (renumbered around the inserted item, with its new parent_id).
 */
export default function MenuItemManager({ menu, posts, pages, categories }) {
  const router = useRouter();
  const propsItems = useMemo(() => menu?.items || [], [menu]);

  const [items, applyOptimistic] = useOptimistic(propsItems, (current, applied) => applied);
  const [savingOrder, startReorder] = useTransition();
  const [orderError, setOrderError] = useState("");

  const tree = useMemo(() => {
    const byParent = new Map();

    for (const item of items) {
      const key = item.parent_id || null;
      if (!byParent.has(key)) {
        byParent.set(key, []);
      }
      byParent.get(key).push(item);
    }

    for (const bucket of byParent.values()) {
      bucket.sort((a, b) => a.sort_order - b.sort_order || a.id - b.id);
    }

    return byParent;
  }, [items]);

  const parentOf = useMemo(() => {
    const map = new Map();

    for (const item of items) {
      map.set(item.id, item.parent_id || null);
    }

    return map;
  }, [items]);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const a11y = useMemo(
    () =>
      createSortableA11y(
        "menu item",
        new Map(items.map((item) => [item.id, item.label || `#${item.id}`])),
      ),
    [items],
  );

  function applyGroups(groups) {
    const touched = new Set();
    const assignedSets = [];

    for (const { parentId, list } of groups) {
      const assigned = list.map((item, index) => ({
        ...item,
        parent_id: parentId,
        sort_order: index,
      }));

      for (const item of assigned) {
        touched.add(item.id);
      }
      assignedSets.push(assigned);
    }

    const merged = [...propsItems.filter((item) => !touched.has(item.id))];
    for (const assigned of assignedSets) {
      merged.push(...assigned);
    }

    const payload = assignedSets.flat().map(({ id, parent_id, sort_order }) => ({
      id,
      parent_id,
      sort_order,
    }));

    setOrderError("");
    startReorder(async () => {
      applyOptimistic(merged);

      try {
        await reorderMenuItemsAction(menu.id, payload);
      } catch (error) {
        setOrderError(error?.message || "The new order could not be saved.");
        router.refresh();
      }
    });
  }

  function isOwnDescendant(candidateParentId, itemId) {
    let cursor = candidateParentId;

    while (cursor != null) {
      if (cursor === itemId) {
        return true;
      }
      cursor = parentOf.get(cursor) ?? null;
    }

    return false;
  }

  function handleDragEnd(event) {
    const { active, over } = event;

    if (!over) {
      return;
    }

    const activeGroup = active.data.current?.parentId ?? null;
    const targetGroup = over.data.current?.parentId ?? null;

    if (isOwnDescendant(targetGroup, active.id)) {
      setOrderError("An item cannot be nested inside its own children.");
      return;
    }

    const sourceList = (tree.get(activeGroup) || []).slice();
    const destinationList = (tree.get(targetGroup) || []).slice();

    if (activeGroup === targetGroup) {
      const from = sourceList.findIndex((item) => item.id === active.id);
      const to = sourceList.findIndex((item) => item.id === over.id);

      if (from === -1 || to === -1 || from === to) {
        return;
      }

      applyGroups([{ parentId: activeGroup, list: arrayMove(sourceList, from, to) }]);
      return;
    }

    const moved = sourceList.find((item) => item.id === active.id);

    if (!moved) {
      return;
    }

    const insertAt = over.data.current?.container
      ? destinationList.length
      : destinationList.findIndex((item) => item.id === over.id);

    if (insertAt === -1) {
      return;
    }

    const destination = [...destinationList];
    destination.splice(insertAt, 0, moved);

    applyGroups([
      { parentId: activeGroup, list: sourceList.filter((item) => item.id !== active.id) },
      { parentId: targetGroup, list: destination },
    ]);
  }

  function renderLevel(parentId, depth) {
    const siblings = tree.get(parentId) || [];

    return (
      <SortableContext
        items={siblings.map((item) => item.id)}
        strategy={verticalListSortingStrategy}
      >
        <GroupContainer parentId={parentId} depth={depth}>
          {siblings.map((item) => (
            <li key={item.id} className="min-w-0">
              <ItemRow
                item={item}
                menu={menu}
                posts={posts}
                pages={pages}
                categories={categories}
              />
              {renderLevel(item.id, depth + 1)}
            </li>
          ))}
        </GroupContainer>
      </SortableContext>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      <section className="rounded-xl border border-border-subtle bg-surface/40 p-4">
        <h2 className="text-sm font-semibold">Add an item to “{menu.name}”</h2>
        <div className="mt-3">
          <ItemForm menu={menu} posts={posts} pages={pages} categories={categories} />
        </div>
      </section>

      <section>
        <header className="flex flex-wrap items-baseline justify-between gap-2">
          <h2 className="text-sm font-semibold">Items ({items.length})</h2>
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
        </header>

        <HelpNote
          noun="menu item"
          nesting="A drag onto another group re-parents it. Keyboard users reorder within a group and set the parent with the “Nested under” select in the item’s settings."
        />

        {items.length ? (
          <DndContext
            sensors={sensors}
            accessibility={a11y}
            collisionDetection={closestCenter}
            onDragEnd={handleDragEnd}
          >
            <div className="mt-3">{renderLevel(null, 0)}</div>
          </DndContext>
        ) : (
          <p className="mt-3 rounded-lg border border-dashed border-border-subtle p-6 text-center text-sm text-muted">
            This menu has no items yet.
          </p>
        )}
      </section>
    </div>
  );
}
