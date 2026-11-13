import Link from "next/link";

function SetupCard({ title, reason }) {
  return (
    <section className="flex flex-col gap-2 rounded-xl border border-border-subtle bg-surface/40 p-4">
      <h2 className="font-semibold">{title}</h2>
      <p className="text-sm text-muted">
        {reason || "Connect this service to see live numbers here."}
      </p>
      <Link
        href="/admin/settings#integrations"
        className="mt-1 self-start text-sm text-accent hover:underline"
      >
        Open settings
      </Link>
    </section>
  );
}

function formatNumber(value) {
  return new Intl.NumberFormat("en-US").format(Number(value) || 0);
}

function formatDuration(seconds) {
  const total = Math.max(0, Math.round(Number(seconds) || 0));
  const minutes = Math.floor(total / 60);
  const rest = total % 60;

  return `${minutes}m ${String(rest).padStart(2, "0")}s`;
}

function Sparkline({ series, valueKey }) {
  const values = (series || []).map((point) => Number(point[valueKey]) || 0);
  const max = Math.max(1, ...values);

  return (
    <div
      className="flex h-12 items-end gap-0.5"
      role="img"
      aria-label={`Daily ${valueKey} trend`}
    >
      {values.length === 0 ? (
        <span className="text-xs text-muted">No data yet.</span>
      ) : (
        values.map((value, index) => (
          <span
            key={index}
            className="min-h-[2px] flex-1 rounded-sm bg-accent/70"
            style={{ height: `${Math.max(4, (value / max) * 100)}%` }}
            title={`${series[index]?.date || ""}: ${value}`}
          />
        ))
      )}
    </div>
  );
}

export function TrafficCard({ data, compact = false }) {
  if (!data || !data.configured) {
    return (
      <SetupCard
        title="Google Analytics"
        reason={data?.reason || "Add your GA4 property to see traffic here."}
      />
    );
  }

  if (data.error) {
    return (
      <section className="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
        <h2 className="font-semibold">Google Analytics</h2>
        <p className="mt-1 text-muted">{data.error}</p>
      </section>
    );
  }

  const totals = data.totals || {};

  return (
    <section className="flex flex-col gap-3 rounded-xl border border-border-subtle bg-surface/40 p-4">
      <div className="flex items-baseline justify-between gap-2">
        <h2 className="font-semibold">Google Analytics</h2>
        <span className="text-xs uppercase tracking-wide text-muted">
          Last {data.range?.days || 28} days
        </span>
      </div>

      <div className="grid grid-cols-2 gap-3 text-sm">
        <p>
          <span className="block text-xs uppercase tracking-wide text-muted">Sessions</span>
          <span className="text-lg font-semibold">{formatNumber(totals.sessions)}</span>
        </p>
        <p>
          <span className="block text-xs uppercase tracking-wide text-muted">Users</span>
          <span className="text-lg font-semibold">{formatNumber(totals.users)}</span>
        </p>
        <p>
          <span className="block text-xs uppercase tracking-wide text-muted">Page views</span>
          <span className="font-medium">{formatNumber(totals.views)}</span>
        </p>
        <p>
          <span className="block text-xs uppercase tracking-wide text-muted">Avg. session</span>
          <span className="font-medium">{formatDuration(totals.avg_session_seconds)}</span>
        </p>
      </div>

      <Sparkline series={data.series} valueKey="sessions" />

      {!compact && (
        <div className="grid gap-4 text-sm sm:grid-cols-2">
          <div>
            <h3 className="text-xs uppercase tracking-wide text-muted">Top pages</h3>
            <ul className="mt-1 divide-y divide-border-subtle">
              {(data.top_pages || []).length ? (
                data.top_pages.map((page) => (
                  <li key={page.path} className="flex justify-between gap-2 py-1">
                    <span className="truncate">{page.path || "/"}</span>
                    <span className="text-muted">{formatNumber(page.views)}</span>
                  </li>
                ))
              ) : (
                <li className="py-1 text-muted">No page views in this range.</li>
              )}
            </ul>
          </div>
          <div>
            <h3 className="text-xs uppercase tracking-wide text-muted">Acquisition</h3>
            <ul className="mt-1 divide-y divide-border-subtle">
              {(data.sources || []).length ? (
                data.sources.map((source) => (
                  <li key={source.channel} className="flex justify-between gap-2 py-1">
                    <span className="truncate">{source.channel}</span>
                    <span className="text-muted">{formatNumber(source.sessions)}</span>
                  </li>
                ))
              ) : (
                <li className="py-1 text-muted">No sessions in this range.</li>
              )}
            </ul>
          </div>
        </div>
      )}
    </section>
  );
}

export function EarningsCard({ data }) {
  if (!data || !data.configured) {
    return (
      <SetupCard
        title="Google AdSense"
        reason={data?.reason || "Add your AdSense account to see earnings here."}
      />
    );
  }

  if (data.error) {
    return (
      <section className="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
        <h2 className="font-semibold">Google AdSense</h2>
        <p className="mt-1 text-muted">{data.error}</p>
      </section>
    );
  }

  const totals = data.totals || {};
  const currency = data.currency || "";

  return (
    <section className="flex flex-col gap-3 rounded-xl border border-border-subtle bg-surface/40 p-4">
      <div className="flex items-baseline justify-between gap-2">
        <h2 className="font-semibold">Google AdSense</h2>
        <span className="text-xs uppercase tracking-wide text-muted">
          Last {data.range?.days || 28} days
        </span>
      </div>

      <div className="grid grid-cols-2 gap-3 text-sm">
        <p>
          <span className="block text-xs uppercase tracking-wide text-muted">Earnings</span>
          <span className="text-lg font-semibold">
            {currency ? `${currency} ` : ""}
            {Number(totals.earnings || 0).toFixed(2)}
          </span>
        </p>
        <p>
          <span className="block text-xs uppercase tracking-wide text-muted">Page RPM</span>
          <span className="font-medium">
            {currency ? `${currency} ` : ""}
            {Number(totals.rpm || 0).toFixed(2)}
          </span>
        </p>
        <p>
          <span className="block text-xs uppercase tracking-wide text-muted">Clicks</span>
          <span className="font-medium">{formatNumber(totals.clicks)}</span>
        </p>
        <p>
          <span className="block text-xs uppercase tracking-wide text-muted">CTR</span>
          <span className="font-medium">{Number(totals.ctr || 0).toFixed(2)}%</span>
        </p>
      </div>

      <Sparkline series={data.series} valueKey="clicks" />
    </section>
  );
}
