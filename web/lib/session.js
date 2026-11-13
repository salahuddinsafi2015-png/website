import "server-only";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";

export const SESSION_COOKIE = "cms_session";

const MAX_AGE = 60 * 60 * 24 * 7;

/**
 * The Sanctum token lives in an httpOnly cookie, so it is never readable from
 * browser JavaScript and never leaves the server boundary.
 */
export async function getToken() {
  const store = await cookies();

  return store.get(SESSION_COOKIE)?.value || null;
}

export async function setToken(token) {
  const store = await cookies();

  store.set(SESSION_COOKIE, token, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    path: "/",
    maxAge: MAX_AGE,
  });
}

export async function clearToken() {
  const store = await cookies();

  store.delete(SESSION_COOKIE);
}

/**
 * Guard for admin routes. Redirects to the login screen when signed out.
 */
export async function requireToken() {
  const token = await getToken();

  if (!token) {
    redirect("/admin/login");
  }

  return token;
}
