import { getToken } from "@/lib/session";

const API_URL = (process.env.API_URL || "http://127.0.0.1:8000").replace(/\/$/, "");

/**
 * Stream a backup through the authenticated session.
 *
 * The API token lives in an httpOnly cookie, so the browser can follow a plain
 * link here while the token itself never reaches client JavaScript.
 */
export async function GET(request, { params }) {
  const token = await getToken();

  if (!token) {
    return new Response("Sign in to download backups.", { status: 401 });
  }

  const { id } = await params;

  if (!/^\d+$/.test(String(id))) {
    return new Response("Not found.", { status: 404 });
  }

  const response = await fetch(`${API_URL}/api/v1/admin/backups/${id}/download`, {
    headers: {
      Accept: "application/octet-stream",
      Authorization: `Bearer ${token}`,
    },
    cache: "no-store",
  });

  if (!response.ok || !response.body) {
    return new Response("The backup could not be downloaded.", {
      status: response.status === 404 ? 404 : 502,
    });
  }

  const headers = new Headers();
  headers.set(
    "Content-Type",
    response.headers.get("content-type") || "application/octet-stream",
  );
  headers.set(
    "Content-Disposition",
    response.headers.get("content-disposition") || "attachment",
  );
  headers.set("Cache-Control", "private, no-store");

  return new Response(response.body, { status: 200, headers });
}
