import "server-only";

import { request, toQuery } from "./api";

const NO_STORE = { cache: "no-store" };

export async function login(email, password) {
  const payload = await request("/auth/login", {
    method: "POST",
    body: { email, password, device_name: "cms-dashboard" },
    cache: "no-store",
  });

  return payload;
}

export async function logout(token) {
  await request("/auth/logout", { method: "POST", token, cache: "no-store" });
}

export async function getMe(token) {
  const payload = await request("/auth/me", { token, ...NO_STORE });

  return payload?.data || null;
}

export async function getDashboard(token) {
  const payload = await request("/admin/dashboard", { token, ...NO_STORE });

  return payload?.data || null;
}

export async function getPosts(token, params = {}) {
  return request(`/admin/posts${toQuery(params)}`, { token, ...NO_STORE });
}

export async function getPost(token, id) {
  const payload = await request(`/admin/posts/${id}`, { token, ...NO_STORE });

  return payload?.data || null;
}

export async function createPost(token, data) {
  return request("/admin/posts", { method: "POST", token, body: data, cache: "no-store" });
}

export async function updatePost(token, id, data) {
  return request(`/admin/posts/${id}`, {
    method: "PATCH",
    token,
    body: data,
    cache: "no-store",
  });
}

export async function trashPost(token, id) {
  return request(`/admin/posts/${id}`, { method: "DELETE", token, cache: "no-store" });
}

export async function restorePost(token, id) {
  return request(`/admin/posts/${id}/restore`, { method: "POST", token, cache: "no-store" });
}

export async function deletePostPermanently(token, id) {
  return request(`/admin/posts/${id}/force`, { method: "DELETE", token, cache: "no-store" });
}

export async function getPostRevisions(token, id, params = {}) {
  return request(`/admin/posts/${id}/revisions${toQuery(params)}`, { token, ...NO_STORE });
}

export async function restorePostRevision(token, id, revisionId) {
  return request(`/admin/posts/${id}/revisions/${revisionId}/restore`, {
    method: "POST",
    token,
    cache: "no-store",
  });
}

export async function getMedia(token, params = {}) {
  return request(`/admin/media${toQuery(params)}`, { token, ...NO_STORE });
}

export async function uploadMedia(token, formData) {
  return request("/admin/media", {
    method: "POST",
    token,
    body: formData,
    cache: "no-store",
  });
}

export async function deleteMedia(token, id) {
  return request(`/admin/media/${id}`, { method: "DELETE", token, cache: "no-store" });
}

export async function getComments(token, params = {}) {
  return request(`/admin/comments${toQuery(params)}`, { token, ...NO_STORE });
}

export async function updateComment(token, id, status) {
  return request(`/admin/comments/${id}`, {
    method: "PATCH",
    token,
    body: { status },
    cache: "no-store",
  });
}

export async function deleteComment(token, id) {
  return request(`/admin/comments/${id}`, { method: "DELETE", token, cache: "no-store" });
}

export async function getTerms(token, params = {}) {
  return request(`/admin/terms${toQuery(params)}`, { token, ...NO_STORE });
}

export async function createTerm(token, data) {
  return request("/admin/terms", { method: "POST", token, body: data, cache: "no-store" });
}

export async function deleteTerm(token, id) {
  return request(`/admin/terms/${id}`, { method: "DELETE", token, cache: "no-store" });
}

export async function getBackups(token) {
  return request("/admin/backups", { token, ...NO_STORE });
}

export async function createBackup(token, data) {
  return request("/admin/backups", { method: "POST", token, body: data, cache: "no-store" });
}

export async function restoreBackup(token, id) {
  return request(`/admin/backups/${id}/restore`, {
    method: "POST",
    token,
    cache: "no-store",
  });
}

export async function deleteBackup(token, id) {
  return request(`/admin/backups/${id}`, { method: "DELETE", token, cache: "no-store" });
}

export async function getAnalyticsOverview(token, params = {}) {
  const payload = await request(`/admin/insights/analytics${toQuery(params)}`, {
    token,
    ...NO_STORE,
  });

  return payload?.data || null;
}

export async function getAdsenseOverview(token, params = {}) {
  const payload = await request(`/admin/insights/adsense${toQuery(params)}`, {
    token,
    ...NO_STORE,
  });

  return payload?.data || null;
}

export async function getSettings(token) {
  return request("/admin/settings", { token, ...NO_STORE });
}

export async function saveSettings(token, settings) {
  return request("/admin/settings", {
    method: "PUT",
    token,
    body: { settings },
    cache: "no-store",
  });
}

export async function getMenus(token) {
  return request("/admin/menus", { token, ...NO_STORE });
}

export async function getMenu(token, id) {
  const payload = await request(`/admin/menus/${id}`, { token, ...NO_STORE });

  return payload?.data || null;
}

export async function createMenu(token, data) {
  return request("/admin/menus", { method: "POST", token, body: data, cache: "no-store" });
}

export async function updateMenu(token, id, data) {
  return request(`/admin/menus/${id}`, {
    method: "PATCH",
    token,
    body: data,
    cache: "no-store",
  });
}

export async function deleteMenu(token, id) {
  return request(`/admin/menus/${id}`, { method: "DELETE", token, cache: "no-store" });
}

export async function createMenuItem(token, menuId, data) {
  return request(`/admin/menus/${menuId}/items`, {
    method: "POST",
    token,
    body: data,
    cache: "no-store",
  });
}

export async function updateMenuItem(token, id, data) {
  return request(`/admin/menu-items/${id}`, {
    method: "PATCH",
    token,
    body: data,
    cache: "no-store",
  });
}

export async function deleteMenuItem(token, id) {
  return request(`/admin/menu-items/${id}`, {
    method: "DELETE",
    token,
    cache: "no-store",
  });
}

export async function reorderMenuItems(token, menuId, items) {
  return request(`/admin/menus/${menuId}/reorder`, {
    method: "PUT",
    token,
    body: { items },
    cache: "no-store",
  });
}

export async function getWidgets(token, params = {}) {
  return request(`/admin/widgets${toQuery(params)}`, { token, ...NO_STORE });
}

export async function createWidget(token, data) {
  return request("/admin/widgets", { method: "POST", token, body: data, cache: "no-store" });
}

export async function updateWidget(token, id, data) {
  return request(`/admin/widgets/${id}`, {
    method: "PATCH",
    token,
    body: data,
    cache: "no-store",
  });
}

export async function deleteWidget(token, id) {
  return request(`/admin/widgets/${id}`, { method: "DELETE", token, cache: "no-store" });
}

export async function reorderWidgets(token, widgets) {
  return request("/admin/widgets/reorder", {
    method: "PUT",
    token,
    body: { widgets },
    cache: "no-store",
  });
}

export async function getThemes(token) {
  const payload = await request("/admin/themes", { token, ...NO_STORE });

  return payload?.data || null;
}

export async function saveTheme(token, data) {
  return request("/admin/themes", {
    method: "PUT",
    token,
    body: data,
    cache: "no-store",
  });
}

export async function getUsers(token, params = {}) {
  return request(`/admin/users${toQuery(params)}`, { token, ...NO_STORE });
}

export async function createUser(token, data) {
  return request("/admin/users", { method: "POST", token, body: data, cache: "no-store" });
}
