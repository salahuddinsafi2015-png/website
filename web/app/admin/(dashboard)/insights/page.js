export const instant = false;

import Link from "next/link";

import { EarningsCard, TrafficCard } from "@/components/admin/InsightsCards";
import { getAdsenseOverview, getAnalyticsOverview } from "@/lib/admin-api";
import { requireToken } from "@/lib/session";

export const metadata = {
  title: "Insights",
  robots: { index: false, follow: false },
};

export default async function InsightsPage() {
  const token = await requireToken();

  const [analytics, adsense] = await Promise.all([
    getAnalyticsOverview(token, { days: 28 }).catch(() => null),
    getAdsenseOverview(token, { days: 28 }).catch(() => null),
  ]);

  const hasAnalyticsProblem = analytics && analytics.configured && analytics.error;
  const hasAdsenseProblem = adsense && adsense.configured && adsense.error;

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Insights</h1>
          <p className="mt-1 text-sm text-muted">
            Traffic from Google Analytics 4 and earnings from Google AdSense, read live
            from Google with your own credentials.
          </p>
        </div>
        <Link
          href="/admin/settings#integrations"
          className="rounded-md border border-border-subtle px-3 py-1.5 text-sm hover:border-accent hover:text-accent"
        >
          Manage integrations
        </Link>
      </header>

      {(hasAnalyticsProblem || hasAdsenseProblem) && (
        <p
          role="alert"
          className="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"
        >
          Google returned an error for a connected service. Confirm the property, the
          AdSense account and the service account permissions, then reload.
        </p>
      )}

      <div className="grid gap-6 lg:grid-cols-2">
        <TrafficCard data={analytics} />
        <EarningsCard data={adsense} />
      </div>

      {analytics?.configured && analytics.series?.length > 0 && (
        <section className="rounded-xl border border-border-subtle p-4">
          <h2 className="font-semibold">Daily traffic</h2>
          <p className="mt-1 text-xs text-muted">
            Sessions per day for the selected range.
          </p>
          <div className="mt-4 flex flex-col gap-2">
            {analytics.series.map((point) => {
              const max = Math.max(
                1,
                ...analytics.series.map((entry) => Number(entry.sessions) || 0),
              );
              const width = Math.max(1, ((Number(point.sessions) || 0) / max) * 100);

              return (
                <div key={point.date} className="flex items-center gap-3 text-xs">
                  <time dateTime={point.date} className="w-24 shrink-0 text-muted">
                    {point.date}
                  </time>
                  <span className="h-2 flex-1 overflow-hidden rounded-full bg-background">
                    <span
                      className="block h-full rounded-full bg-accent"
                      style={{ width: `${width}%` }}
                    />
                  </span>
                  <span className="w-14 shrink-0 text-right tabular-nums">
                    {point.sessions}
                  </span>
                </div>
              );
            })}
          </div>
        </section>
      )}

      <section className="rounded-xl border border-border-subtle p-4 text-sm text-muted">
        <h2 className="font-semibold text-foreground">Connecting Google services</h2>
        <ol className="mt-2 list-decimal space-y-1 pl-5">
          <li>
            In Google Cloud, create a service account, enable the <em>Google Analytics
            Data API</em> and the <em>AdSense Management API</em>, then download a JSON key.
          </li>
          <li>
            In Google Analytics, add the service account email as a viewer on your GA4
            property and paste the numeric property ID into settings.
          </li>
          <li>
            In AdSense, grant the same email access to the account and paste the account
            ID (accounts/pub-…) into settings.
          </li>
          <li>
            Paste the JSON key into <Link className="text-accent hover:underline" href="/admin/settings#integrations">Settings → Integrations</Link>.
            The key is stored privately and never exposed to visitors.
          </li>
        </ol>
      </section>
    </div>
  );
}
