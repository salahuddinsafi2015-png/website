export const instant = false;

import Link from "next/link";

import { getAdsenseOverview, getAnalyticsOverview, getDashboard } from "@/lib/admin-api";
import { requireToken } from "@/lib/session";
import { EarningsCard, TrafficCard } from "@/components/admin/InsightsCards";

function StatCard({ label, value, href }) {
  const body = (
    <div className="rounded-xl border border-border-subtle bg-surface/40 p-4">
      <p className="text-xs uppercase tracking-widest text-muted">{label}</p>
      <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
  );

  return href ? (
    <Link href={href} className="block transition hover:border-accent">
      {body}
    </Link>
  ) : (
    body
  );
}

export default async function DashboardPage() {
  const token = await requireToken();
  const [data, analytics, adsense] = await Promise.all([
    getDashboard(token).catch(() => null),
    getAnalyticsOverview(token, { days: 28 }).catch(() => null),
    getAdsenseOverview(token, { days: 28 }).catch(() => null),
  ]);

  if (!data) {
    return (
      <p className="rounded-lg border border-red-500/40 bg-red-500/10 p-4 text-sm">
        The dashboard could not be loaded. Check that the API is running.
      </p>
    );
  }

  const { stats, recent_posts: recentPosts, recent_comments: recentComments } = data;

  return (
    <div className="flex flex-col gap-8">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Overview</h1>
        <p className="mt-1 text-sm text-muted">A snapshot of the site&rsquo;s content.</p>
      </header>

      <section className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <StatCard label="Posts" value={stats.posts} href="/admin/posts?type=post" />
        <StatCard label="Pages" value={stats.pages} href="/admin/posts?type=page" />
        <StatCard label="Published" value={stats.published} href="/admin/posts?status=publish" />
        <StatCard label="Drafts" value={stats.drafts} href="/admin/posts?status=draft" />
        <StatCard label="Scheduled" value={stats.scheduled} href="/admin/posts?status=future" />
        <StatCard
          label="Comments to review"
          value={stats.comments_pending}
          href="/admin/comments?status=pending"
        />
        <StatCard label="Media" value={stats.media} href="/admin/media" />
        <StatCard label="Users" value={stats.users} href="/admin/users" />
      </section>

      <div className="grid gap-6 lg:grid-cols-2">
        <TrafficCard data={analytics} compact />
        <EarningsCard data={adsense} />
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <section className="rounded-xl border border-border-subtle p-5">
          <div className="flex items-center justify-between">
            <h2 className="font-semibold">Recently updated</h2>
            <Link href="/admin/posts/new" className="text-sm text-accent hover:underline">
              New post
            </Link>
          </div>
          <ul className="mt-4 flex flex-col divide-y divide-border-subtle text-sm">
            {recentPosts?.length ? (
              recentPosts.map((post) => (
                <li key={post.id} className="flex items-center justify-between gap-3 py-2">
                  <Link href={`/admin/posts/${post.id}`} className="truncate hover:text-accent">
                    {post.title || "(untitled)"}
                  </Link>
                  <span className="shrink-0 text-xs uppercase tracking-wide text-muted">
                    {post.status}
                  </span>
                </li>
              ))
            ) : (
              <li className="py-2 text-muted">Nothing published yet.</li>
            )}
          </ul>
        </section>

        <section className="rounded-xl border border-border-subtle p-5">
          <div className="flex items-center justify-between">
            <h2 className="font-semibold">Latest comments</h2>
            <Link href="/admin/comments" className="text-sm text-accent hover:underline">
              Moderate
            </Link>
          </div>
          <ul className="mt-4 flex flex-col divide-y divide-border-subtle text-sm">
            {recentComments?.length ? (
              recentComments.map((comment) => (
                <li key={comment.id} className="py-2">
                  <p className="font-medium">{comment.author_name}</p>
                  <p className="text-muted">{comment.content}</p>
                  <p className="mt-1 text-xs uppercase tracking-wide text-muted">
                    {comment.status}
                  </p>
                </li>
              ))
            ) : (
              <li className="py-2 text-muted">No comments yet.</li>
            )}
          </ul>
        </section>
      </div>
    </div>
  );
}