/**
 * dnd-kit ships generic English announcements; this factory replaces them
 * with ones built from the real item labels, and states the exact keys used
 * so screen-reader users get the same instructions as sighted ones.
 *
 * The announcement strings deliberately stay neutral about the outcome: the
 * server may still reject the save, and the header's aria-live status is
 * what reports "Saving order…" and errors.
 *
 * @param {string} noun e.g. "menu item" or "widget"
 * @param {Map<number,string>} labels id to display label
 */
export function createSortableA11y(noun, labels) {
  const capitalised = noun.charAt(0).toUpperCase() + noun.slice(1);
  // Callers may key the Map by string ids (block editor) or numeric ones
  // (menus, widgets); try both so an unmapped id falls back cleanly.
  const label = (id) => labels.get(id) ?? labels.get(Number(id)) ?? noun;
  const isContainer = (active) => Boolean(active?.data?.current?.container);

  return {
    screenReaderInstructions: {
      draggable: `${capitalised}s are sortable. Put focus on a reorder handle and press Space or Enter to lift it, use the arrow keys to move it within its group, then press Space or Enter to drop it — or Escape to cancel. To move it into a different group, use the ${noun} form field for that instead.`,
    },
    announcements: {
      onDragStart: (event) =>
        `${capitalised} ${label(event.active.id)} picked up. Use the arrow keys to move it, press Space or Enter to drop it, or Escape to cancel.`,
      onDragOver: (event) =>
        event.over
          ? `${capitalised} ${label(event.active.id)} is over ${
              isContainer(event.over) ? `the empty ${noun} group` : noun
            } ${label(event.over.id)}.`
          : `${capitalised} ${label(event.active.id)} is not over any group.`,
      onDragEnd: (event) =>
        `${capitalised} ${label(event.active.id)} dropped. The order is saving.`,
      onDragCancel: (event) =>
        `${capitalised} ${label(event.active.id)} move cancelled.`,
    },
  };
}

/**
 * Visible, keyboard-complete instructions, rendered inline next to the list.
 *
 * @param {{ noun: string, nesting?: string }} props
 */
export function HelpNote({ noun, nesting }) {
  const kbd =
    "rounded border border-border-subtle bg-surface px-1.5 py-0.5 font-mono text-[0.7rem] leading-none";

  return (
    <div className="rounded-lg border border-border-subtle bg-surface/40 px-3 py-2 text-xs text-muted">
      <p>
        <strong className="font-semibold text-foreground">Reorder:</strong> drag
        the <span aria-hidden="true">≡</span> handle, or focus it with{" "}
        <kbd className={kbd}>Tab</kbd> and press <kbd className={kbd}>Space</kbd>,
        move with <kbd className={kbd}>↑</kbd>{" "}
        <kbd className={kbd}>↓</kbd>, drop with{" "}
        <kbd className={kbd}>Space</kbd>, cancel with{" "}
        <kbd className={kbd}>Esc</kbd>.
      </p>
      {nesting && (
        <p className="mt-1">
          <strong className="font-semibold text-foreground">Nesting:</strong>{" "}
          {nesting}
        </p>
      )}
    </div>
  );
}
