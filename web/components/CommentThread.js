function CommentItem({ comment, depth = 0 }) {
  const children = comment.children || [];

  return (
    <li className={depth > 0 ? "mt-4 border-l border-border-subtle pl-4" : "mt-6"}>
      <article className="rounded-lg bg-surface/50 p-4">
        <header className="flex flex-wrap items-baseline gap-2 text-sm">
          <span className="font-semibold">{comment.author_name}</span>
          <time className="text-xs text-muted" dateTime={comment.created_at}>
            {new Date(comment.created_at).toLocaleDateString("en-US", {
              year: "numeric",
              month: "short",
              day: "numeric",
            })}
          </time>
        </header>
        <p className="mt-2 whitespace-pre-line text-sm leading-relaxed">{comment.content}</p>
      </article>
      {children.length > 0 && (
        <ul>
          {children.map((child) => (
            <CommentItem key={child.id} comment={child} depth={depth + 1} />
          ))}
        </ul>
      )}
    </li>
  );
}

export default function CommentThread({ comments }) {
  if (!comments.length) {
    return <p className="text-sm text-muted">No comments yet. Be the first to reply.</p>;
  }

  return (
    <ul className="not-prose">
      {comments.map((comment) => (
        <CommentItem key={comment.id} comment={comment} />
      ))}
    </ul>
  );
}
