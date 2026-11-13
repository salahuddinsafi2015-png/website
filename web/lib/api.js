import "server-only";

const API_URL = (process.env.API_URL || "http://127.0.0.1:8000").replace(/\/$/, "");

export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }
}

/**
 * Low-level request helper.
 *
 * `cache: "no-store"` is used for anything session-specific (admin data), so
 * responses are never shared between accounts. Public content is cached one
 * level up by the `use cache` functions in lib/content.js.
 */
export async function request(
  path,
  { method = "GET", token, body, headers = {}, cache, signal } = {},
) {
  const isFormData = typeof FormData !== "undefined" && body instanceof FormData;

  const init = {
    method,
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(isFormData ? {} : body ? { "Content-Type": "application/json" } : {}),
      ...headers,
    },
    body: isFormData ? body : body ? JSON.stringify(body) : undefined,
    signal,
  };

  if (cache) {
    init.cache = cache;
  }

  const response = await fetch(`${API_URL}/api/v1${path}`, init);

  const payload =
    response.status === 204
      ? null
      : await response.json().catch(() => null);

  if (!response.ok) {
    throw new ApiError(
      payload?.message || `Request failed with status ${response.status}`,
      response.status,
      payload?.errors || {},
    );
  }

  return payload;
}

/**
 * Build a query string, skipping empty values.
 */
export function toQuery(params = {}) {
  const search = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      search.set(key, String(value));
    }
  });

  const query = search.toString();

  return query ? `?${query}` : "";
}
