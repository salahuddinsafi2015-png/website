"use client";

import Image from "next/image";
import { useMemo, useState } from "react";
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

import { createSortableA11y, HelpNote } from "@/lib/dnd-a11y";

const TYPE_OPTIONS = [
  ["paragraph", "Paragraph"],
  ["heading", "Heading"],
  ["image", "Image"],
  ["quote", "Quote"],
  ["list", "List"],
  ["code", "Code"],
  ["divider", "Divider"],
  ["html", "Rich HTML"],
];

const TYPE_LABEL = new Map(TYPE_OPTIONS);

const FIELD_CLASSES =
  "w-full rounded-md border border-border-subtle bg-background px-3 py-1.5 text-sm";

function defaults(type) {
  switch (type) {
    case "heading":
      return { level: 2, text: "" };
    case "image":
      return { src: "", alt: "", caption: "" };
    case "quote":
      return { text: "", citation: "" };
    case "list":
      return { style: "bulleted", items: [] };
    case "code":
      return { language: "", code: "" };
    case "paragraph":
      return { text: "" };
    case "html":
      return { content: "" };
    case "divider":
    default:
      return {};
  }
}

function carriesText(from, to) {
  return from === "paragraph" && to === "quote";
}

function seedBlocks(serverBlocks) {
  return (serverBlocks || [])
    .filter((block) => block && TYPE_LABEL.has(block.type))
    .map((block, index) => ({
      ...defaults(block.type),
      ...block,
      type: block.type,
      id: `blk-seed-${index + 1}`,
    }));
}

let seedCounter = 0;

function cloneBlock(type, source) {
  seedCounter += 1;
  return {
    ...defaults(type),
    ...(source && carriesText(source.type, type) ? { text: source.text } : null),
    type,
    id: `blk-new-${seedCounter}-${Math.random().toString(36).slice(2, 7)}`,
  };
}

function PlainNode({ block }) {
  switch (block.type) {
    case "heading": {
      const level = Math.min(6, Math.max(1, Number(block.level) || 2));
      const Tag = `h${level}`;
      return <Tag>{block.text}</Tag>;
    }
    case "paragraph":
      return <p>{block.text}</p>;
    case "quote":
      return (
        <blockquote>
          <p>{block.text}</p>
          {block.citation ? <cite>{block.citation}</cite> : null}
        </blockquote>
      );
    case "list": {
      const Tag = block.style === "numbered" ? "ol" : "ul";
      return (
        <Tag>
          {(block.items || []).map((item, index) => (
            <li key={index}>{item}</li>
          ))}
        </Tag>
      );
    }
    case "image":
      return (
        <figure>
          {block.src ? (
            <Image
              src={block.src}
              alt={block.alt || ""}
              width={640}
              height={360}
              unoptimized
            />
          ) : (
            <p className="text-xs text-muted">No image URL yet.</p>
          )}
          {block.caption ? (
            <figcaption>{block.caption}</figcaption>
          ) : null}
        </figure>
      );
    case "code":
      return <pre><code>{block.code}</code></pre>;
    case "divider":
      return <hr />;
    case "html":
      return null;
    default:
      return null;
  }
}

function BlockFields({ block, onChange }) {
  switch (block.type) {
    case "paragraph":
    case "quote":
    case "heading":
    case "image":
    case "list":
    case "code":
    case "html":
    case "divider":
      break;
    default:
      return null;
  }

  const textLabel = {
    paragraph: "Text",
    heading: "Heading text",
    quote: "Quoted text",
    code: "Code",
  }[block.type];

  return (
    <div className="flex flex-col gap-3">
      {block.type === "divider" ? (
        <p className="text-xs text-muted">Renders a horizontal rule.</p>
      ) : null}

      {block.type === "heading" && (
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">Level</span>
          <select
            aria-label="Heading level"
            value={block.level || 2}
            onChange={(event) => onChange({ level: Number(event.target.value) })}
            className={FIELD_CLASSES}
          >
            {[1, 2, 3, 4, 5, 6].map((level) => (
              <option key={level} value={level}>
                H{level}
              </option>
            ))}
          </select>
        </label>
      )}

      {textLabel && block.type !== "code" ? (
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs uppercase tracking-wide text-muted">{textLabel}</span>
          <textarea
            aria-label={textLabel}
            rows={block.type === "paragraph" || block.type === "quote" ? 3 : 2}
            value={block.text || ""}
            onChange={(event) => onChange({ text: event.target.value })}
            className={FIELD_CLASSES}
            maxLength={block.type === "paragraph" || block.type === "quote" ? 10000 : 500}
          />
        </label>
      ) : null}

      {block.type === "image" && (
        <>
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">
              Image URL (site path or https://)
            </span>
            <input
              aria-label="Image URL"
              type="text"
              value={block.src || ""}
              onChange={(event) => onChange({ src: event.target.value })}
              className={FIELD_CLASSES}
              maxLength={2048}
              placeholder="/media/… or https://…"
            />
          </label>
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">
              Alt text (accessibility)
            </span>
            <input
              aria-label="Image alt text"
              type="text"
              value={block.alt || ""}
              onChange={(event) => onChange({ alt: event.target.value })}
              className={FIELD_CLASSES}
              maxLength={500}
            />
          </label>
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">Caption</span>
            <input
              aria-label="Image caption"
              type="text"
              value={block.caption || ""}
              onChange={(event) => onChange({ caption: event.target.value })}
              className={FIELD_CLASSES}
              maxLength={2000}
            />
          </label>
        </>
      )}

      {block.type === "list" && (
        <>
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">List style</span>
            <select
              aria-label="List style"
              value={block.style || "bulleted"}
              onChange={(event) => onChange({ style: event.target.value })}
              className={FIELD_CLASSES}
            >
              <option value="bulleted">Bulleted (ul)</option>
              <option value="numbered">Numbered (ol)</option>
            </select>
          </label>
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">
              Items, one per line
            </span>
            <textarea
              aria-label="List items"
              rows={4}
              value={(block.items || []).join("\n")}
              onChange={(event) =>
                onChange({ items: event.target.value.split("\n").map((item) => item.trim()) })
              }
              className={FIELD_CLASSES}
            />
          </label>
        </>
      )}

      {block.type === "code" && (
        <>
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">
              Language (optional)
            </span>
            <input
              aria-label="Code language"
              type="text"
              value={block.language || ""}
              onChange={(event) => onChange({ language: event.target.value })}
              className={FIELD_CLASSES}
              maxLength={32}
              placeholder="php, js, css…"
            />
          </label>
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">Code</span>
            <textarea
              aria-label="Code"
              rows={6}
              value={block.code || ""}
              onChange={(event) => onChange({ code: event.target.value })}
              className={`${FIELD_CLASSES} font-mono text-xs`}
              maxLength={50000}
            />
          </label>
        </>
      )}

      {block.type === "html" && (
        <>
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">HTML</span>
            <textarea
              aria-label="Rich HTML"
              rows={6}
              value={block.content || ""}
              onChange={(event) => onChange({ content: event.target.value })}
              className={`${FIELD_CLASSES} font-mono text-xs`}
              maxLength={100000}
              placeholder="<p>This block is sanitised server-side.</p>"
            />
          </label>
        </>
      )}
    </div>
  );
}

function BlockCard({ block, index, onChange, onRemove }) {
  const {
    attributes,
    listeners,
    setNodeRef,
    setActivatorNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: block.id, data: { parent: "blocks" } });

  const label = TYPE_LABEL.get(block.type) || "Block";

  return (
    <li
      ref={setNodeRef}
      style={{
        transform: CSS.Translate.toString(transform),
        transition,
      }}
      className={`rounded-xl border border-border-subtle p-3 ${
        isDragging ? "z-10 bg-surface shadow-lg" : ""
      }`}
    >
      <details>
        <summary className="flex cursor-pointer flex-wrap items-center justify-between gap-2 text-sm">
          <span className="flex min-w-0 items-center gap-2 font-medium">
            <button
              type="button"
              ref={setActivatorNodeRef}
              aria-label={`Drag to reorder ${label} ${index + 1}`}
              className="cursor-grab touch-none select-none rounded text-muted ring-offset-2 transition hover:text-foreground focus-visible:ring-2 focus-visible:ring-accent"
              {...attributes}
              {...listeners}
            >
              ≡
            </button>
            {label}
            {index + 1}
          </span>
          <button
            type="button"
            onClick={() => onRemove(block.id)}
            className="rounded border border-red-500/40 px-2 py-1 text-xs text-red-600 hover:bg-red-500/10 dark:text-red-400"
          >
            Remove
          </button>
        </summary>

        <div className="mt-3">
          <label className="mb-3 flex flex-col gap-1 text-sm">
            <span className="text-xs uppercase tracking-wide text-muted">Block type</span>
            <select
              aria-label="Block type"
              value={block.type}
              onChange={(event) =>
                onChange(block.id, cloneBlock(event.target.value, block), true)
              }
              className={FIELD_CLASSES}
            >
              {TYPE_OPTIONS.map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>

          <BlockFields
            block={block}
            onChange={(patch) => onChange(block.id, patch, false)}
          />
        </div>
      </details>
    </li>
  );
}

/**
 * The block editor submits two hidden fields the server action understands:
 * `blocks` (a JSON list) and `content` (only populated in HTML mode). The
 * server treats a non-empty blocks list as the source of truth and renders it
 * into sanitised HTML; an empty list falls back to classic content editing.
 */
export default function BlockEditor({ initialBlocks, initialContent }) {
  const seed = useMemo(() => seedBlocks(initialBlocks), [initialBlocks]);
  const [blocks, setBlocks] = useState(seed);
  const [mode, setMode] = useState(seed.length > 0 || !initialContent ? "blocks" : "html");
  const [html, setHtml] = useState(initialContent || "");

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const a11y = useMemo(
    () =>
      createSortableA11y(
        "block",
        new Map(
          blocks.map((block, index) => [
            block.id,
            `${TYPE_LABEL.get(block.type) || "Block"} ${index + 1}`,
          ]),
        ),
      ),
    [blocks],
  );

  const labels = new Map(blocks.map((block) => [block.id, TYPE_LABEL.get(block.type)]));

  function handleDragEnd(event) {
    const { active, over } = event;

    if (!over || active.id === over.id) {
      return;
    }

    setBlocks((current) => {
      const from = current.findIndex((block) => block.id === active.id);
      const to = current.findIndex((block) => block.id === over.id);

      if (from === -1 || to === -1) {
        return current;
      }

      return arrayMove(current, from, to);
    });
  }

  function addBlock(type) {
    setBlocks((current) => [...current, cloneBlock(type, null)]);
  }

  function changeBlock(id, patch, replace) {
    setBlocks((current) =>
      current.map((block) => {
        if (block.id !== id) {
          return block;
        }
        return replace ? { ...patch } : { ...block, ...patch };
      }),
    );
  }

  function removeBlock(id) {
    setBlocks((current) => current.filter((block) => block.id !== id));
  }

  const stripIds = () =>
    blocks.map(({ id, ...rest }) => rest);

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-sm font-semibold">Content</h2>
        <div
          role="group"
          aria-label="Editor mode"
          className="flex rounded-md border border-border-subtle p-0.5 text-xs"
        >
          <button
            type="button"
            aria-pressed={mode === "blocks"}
            onClick={() => setMode("blocks")}
            className={`rounded px-3 py-1 ${
              mode === "blocks" ? "bg-accent text-accent-contrast" : "hover:bg-surface"
            }`}
          >
            Blocks
          </button>
          <button
            type="button"
            aria-pressed={mode === "html"}
            onClick={() => setMode("html")}
            className={`rounded px-3 py-1 ${
              mode === "html" ? "bg-accent text-accent-contrast" : "hover:bg-surface"
            }`}
          >
            HTML
          </button>
        </div>
      </div>

      {mode === "blocks" ? (
        <>
          <input
            type="hidden"
            name="blocks"
            value={JSON.stringify(stripIds())}
          />
          <input type="hidden" name="content" value="" />

          <p className="text-xs text-muted">
            Blocks are rendered into sanitised HTML on the server. Switch to the
            HTML tab for a post (or start it empty) to edit raw markup instead.
          </p>

          <HelpNote noun="block" nesting="Blocks form one flat list; heading level lives inside each heading block." />

          <div className="flex flex-wrap gap-2">
            {TYPE_OPTIONS.map(([type, label]) => (
              <button
                key={type}
                type="button"
                onClick={() => addBlock(type)}
                className="rounded-md border border-border-subtle px-2.5 py-1 text-xs hover:border-accent"
              >
                + {label}
              </button>
            ))}
          </div>

          {blocks.length ? (
            <DndContext
              sensors={sensors}
              accessibility={a11y}
              collisionDetection={closestCenter}
              onDragEnd={handleDragEnd}
            >
              <SortableContext
                items={blocks.map((block) => block.id)}
                strategy={verticalListSortingStrategy}
              >
                <ul className="flex flex-col gap-2">
                  {blocks.map((block, index) => (
                    <BlockCard
                      key={block.id}
                      block={block}
                      index={index}
                      onChange={changeBlock}
                      onRemove={removeBlock}
                    />
                  ))}
                </ul>
              </SortableContext>
            </DndContext>
          ) : (
            <p className="rounded-lg border border-dashed border-border-subtle p-6 text-center text-sm text-muted">
              No blocks yet — add one above.
            </p>
          )}

          {blocks.length > 0 && (
            <section
              aria-label="Content preview"
              className="rounded-xl border border-border-subtle bg-surface/40 p-4"
            >
              <h3 className="text-xs uppercase tracking-widest text-muted">Preview</h3>
              <div className="prose mt-2 flex flex-col gap-2 text-sm">
                {blocks.map((block) => (
                  <PlainPreview key={block.id} block={block} labels={labels} />
                ))}
              </div>
            </section>
          )}
        </>
      ) : (
        <>
          <input type="hidden" name="blocks" value="" />
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium">Content (HTML)</span>
            <textarea
              name="content"
              rows={18}
              value={html}
              onChange={(event) => setHtml(event.target.value)}
              className="rounded-md border border-border-subtle bg-background px-3 py-2 font-mono text-sm"
              placeholder="<p>Write your story…</p>"
            />
            <span className="text-xs text-muted">
              HTML is sanitised on the server before it is stored.
            </span>
          </label>
        </>
      )}
    </div>
  );
}

/**
 * Same renderer as the in-card preview, but text-only and inert so the
 * preview never becomes an attack surface: it inserts no HTML.
 */
function PlainPreview({ block, labels }) {
  if (block.type === "html") {
    return (
      <p className="text-muted">
        {labels.get(block.id)}: rendered server-side after sanitising; open this
        block to inspect its markup.
      </p>
    );
  }

  return <PlainNode block={block} />;
}
