const API_URL = (process.env.API_URL || "http://127.0.0.1:8000").replace(/\/$/, "");

/**
 * Readiness probe for the frontend: confirms the app is serving and the API
 * answers its own health check. Returns no configuration details.
 */
export async function GET() {
  let api = "unreachable";

  try {
    const response = await fetch(`${API_URL}/api/v1/health`, {
      cache: "no-store",
      signal: AbortSignal.timeout(3000),
    });

    api = response.ok ? "ok" : `error:${response.status}`;
  } catch {
    api = "unreachable";
  }

  const healthy = api === "ok";

  return Response.json(
    {
      status: healthy ? "ok" : "degraded",
      checks: { app: "ok", api },
      time: new Date().toISOString(),
    },
    { status: healthy ? 200 : 503, headers: { "Cache-Control": "no-store" } },
  );
}
