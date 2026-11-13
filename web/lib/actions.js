"use server";

import { revalidatePath, revalidateTag } from "next/cache";
import { redirect } from "next/navigation";

import {
  createBackup,
  createMenuItem,
  createMenu,
  createPost,
  createTerm,
  createUser,
  createWidget,
  deleteBackup,
  deleteComment,
  deleteMedia,
  deleteMenuItem,
  deleteMenu,
  deletePostPermanently,
  deleteTerm,
  deleteWidget,
  login,
  logout,
  reorderMenuItems,
  reorderWidgets,
  restoreBackup,
  restorePost,
  restorePostRevision,
  saveSettings,
  saveTheme,
  trashPost,
  updateComment,
  updateMenuItem,
  updateMenu,
  updatePost,
  updateWidget,
  uploadMedia,
} from "./admin-api";
import { ApiError, request } from "./api";
import { clearToken, getToken, setToken } from "./session";

/**
 * Translate API failures into a shape useActionState can render.
 */
function toActionError(error, fallback = "Something went wrong. Please try again.") {
  if (error instanceof ApiError) {
    const firstValidationError = Object.values(error.errors || {})?.[0]?.[0];

    return {
      error: firstValidationError || error.message || fallback,
      fieldErrors: error.errors || {},
    };
  }

  if (error?.digest?.startsWith("NEXT_REDIRECT")) {
    throw error;
  }

  return { error: fallback, fieldErrors: {} };
}

/**
 * Same translation, for reorder actions that surface the problem to the
 * component that called them instead of rendering a form message.
 */
function toActionMessage(error, fallback) {
  if (error instanceof ApiError) {
    const firstValidationError = Object.values(error.errors || {})?.[0]?.[0];

    return firstValidationError || error.message || fallback;
  }

  return fallback;
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

export async function loginAction(prevState, formData) {
  const email = String(formData.get("email") || "").trim();
  const password = String(formData.get("password") || "");

  if (!email || !password) {
    return { error: "Enter your email address and password." };
  }

  let token;

  try {
    const payload = await login(email, password);
    token = payload?.token;
  } catch (error) {
    return toActionError(error, "Those credentials did not work.");
  }

  if (!token) {
    return { error: "The server did not return a session token." };
  }

  await setToken(token);
  redirect("/admin");
}

export async function logoutAction() {
  const token = await getToken();

  if (token) {
    try {
      await logout(token);
    } catch {
      // Token may already be revoked; clearing the cookie is what matters.
    }
  }

  await clearToken();
  redirect("/admin/login");
}

/*
|--------------------------------------------------------------------------
| Posts
|--------------------------------------------------------------------------
*/

export async function savePostAction(prevState, formData) {
  const token = await getToken();

  if (!token) {
    return { error: "Your session expired. Sign in again." };
  }

  const id = formData.get("id");
  const status = String(formData.get("status") || "draft");
  const slug = String(formData.get("slug") || "").trim();
  const publishedAt = String(formData.get("published_at") || "");

  const rawBlocks = String(formData.get("blocks") || "").trim();
  let blocksPayload = null;

  if (rawBlocks) {
    try {
      blocksPayload = JSON.parse(rawBlocks);
    } catch {
      return {
        error: "The block data did not survive the round trip. Reload the editor and try again.",
      };
    }
  }

  const payload = {
    title: String(formData.get("title") || ""),
    slug: slug || null,
    excerpt: String(formData.get("excerpt") || "") || null,
    content: String(formData.get("content") || ""),
    blocks: blocksPayload,
    status,
    type: String(formData.get("type") || "post"),
    comment_status: String(formData.get("comment_status") || "open"),
    published_at: publishedAt ? new Date(publishedAt).toISOString() : null,
    term_ids: formData.getAll("term_ids").map((value) => Number(value)).filter(Boolean),
  };

  let saved;

  try {
    saved = id
      ? await updatePost(token, id, payload)
      : await createPost(token, payload);
  } catch (error) {
    return toActionError(error, "The post could not be saved.");
  }

  revalidateTag("posts", "max");
  revalidateTag("comments", "max");
  revalidatePath("/");
  revalidatePath("/posts");
  revalidatePath("/admin/posts");

  if (saved?.data?.slug) {
    revalidatePath(`/posts/${saved.data.slug}`);
  }

  redirect("/admin/posts?saved=1");
}

export async function trashPostAction(formData) {
  const token = await getToken();
  const id = formData.get("id");

  if (!token || !id) {
    return;
  }

  await trashPost(token, id);
  revalidateTag("posts", "max");
  revalidatePath("/admin/posts");
  revalidatePath("/posts");
}

export async function restorePostAction(formData) {
  const token = await getToken();
  const id = formData.get("id");

  if (!token || !id) {
    return;
  }

  await restorePost(token, id);
  revalidatePath("/admin/posts");
}

export async function restoreRevisionAction(prevState, formData) {
  const token = await getToken();

  if (!token) {
    return { error: "Your session expired. Sign in again." };
  }

  const postId = String(formData.get("id") || "");
  const revisionId = String(formData.get("revision_id") || "");

  if (!postId || !revisionId) {
    return { error: "That revision is no longer available." };
  }

  try {
    await restorePostRevision(token, postId, revisionId);
  } catch (error) {
    return toActionError(error, "The revision could not be restored.");
  }

  revalidateTag("posts", "max");
  revalidateTag("comments", "max");

  return { message: `Restored revision #${revisionId}. The editor now shows its content.` };
}

export async function destroyPostAction(formData) {
  const token = await getToken();
  const id = formData.get("id");

  if (!token || !id) {
    return;
  }

  await deletePostPermanently(token, id);
  revalidatePath("/admin/posts");
}

/*
|--------------------------------------------------------------------------
| Media
|--------------------------------------------------------------------------
*/

export async function uploadMediaAction(prevState, formData) {
  const token = await getToken();

  if (!token) {
    return { error: "Your session expired. Sign in again." };
  }

  const file = formData.get("file");

  if (!file || typeof file === "string" || file.size === 0) {
    return { error: "Choose a file to upload." };
  }

  const body = new FormData();
  body.set("file", file);
  body.set("alt_text", String(formData.get("alt_text") || ""));
  body.set("title", String(formData.get("title") || ""));

  try {
    await uploadMedia(token, body);
  } catch (error) {
    return toActionError(error, "The upload failed.");
  }

  revalidatePath("/admin/media");

  return { success: "File uploaded." };
}

export async function deleteMediaAction(formData) {
  const token = await getToken();
  const id = formData.get("id");

  if (!token || !id) {
    return;
  }

  await deleteMedia(token, id);
  revalidatePath("/admin/media");
}

/*
|--------------------------------------------------------------------------
| Comments
|--------------------------------------------------------------------------
*/

export async function moderateCommentAction(formData) {
  const token = await getToken();
  const id = formData.get("id");
  const status = String(formData.get("status") || "");

  if (!token || !id || !status) {
    return;
  }

  await updateComment(token, id, status);
  revalidateTag("comments", "max");
  revalidatePath("/admin/comments");
}

export async function deleteCommentAction(formData) {
  const token = await getToken();
  const id = formData.get("id");

  if (!token || !id) {
    return;
  }

  await deleteComment(token, id);
  revalidateTag("comments", "max");
  revalidatePath("/admin/comments");
}

export async function submitCommentAction(prevState, formData) {
  const slug = String(formData.get("slug") || "");

  try {
    const payload = await request(`/posts/${encodeURIComponent(slug)}/comments`, {
      method: "POST",
      body: {
        author_name: String(formData.get("author_name") || ""),
        author_email: String(formData.get("author_email") || ""),
        author_url: String(formData.get("author_url") || "") || null,
        content: String(formData.get("content") || ""),
      },
      cache: "no-store",
    });

    revalidateTag("comments", "max");
    revalidatePath(`/posts/${slug}`);

    return {
      success: payload?.message || "Your comment has been submitted.",
      error: null,
    };
  } catch (error) {
    return toActionError(error, "The comment could not be submitted.");
  }
}

/*
|--------------------------------------------------------------------------
| Terms and settings
|--------------------------------------------------------------------------
*/

export async function createTermAction(prevState, formData) {
  const token = await getToken();

  if (!token) {
    return { error: "Your session expired. Sign in again." };
  }

  try {
    await createTerm(token, {
      taxonomy: String(formData.get("taxonomy") || "category"),
      name: String(formData.get("name") || ""),
      description: String(formData.get("description") || "") || null,
      parent_id: formData.get("parent_id") ? Number(formData.get("parent_id")) : null,
    });
  } catch (error) {
    return toActionError(error, "The term could not be created.");
  }

  revalidateTag("terms", "max");
  revalidatePath("/admin/terms");

  return { success: "Term created." };
}

export async function deleteTermAction(formData) {
  const token = await getToken();
  const id = formData.get("id");

  if (!token || !id) {
    return;
  }

  await deleteTerm(token, id);
  revalidateTag("terms", "max");
  revalidatePath("/admin/terms");
}

export async function saveSettingsAction(prevState, formData) {
  const token = await getToken();

  if (!token) {
    return { error: "Your session expired. Sign in again." };
  }

  const fields = [
    ["site.name", "string", true],
    ["site.description", "string", true],
    ["site.tagline", "string", true],
    ["site.posts_per_page", "integer", true],
    ["seo.default_title_suffix", "string", true],
    ["seo.default_description", "string", true],
    ["comments.require_moderation", "boolean", false],
    ["users.allow_registration", "boolean", true],
    // Tracking IDs are public (they ship in page source); credentials are not.
    ["analytics.measurement_id", "string", true],
    ["analytics.property_id", "string", false],
    ["analytics.credentials_json", "string", false],
    ["adsense.client_id", "string", true],
    ["adsense.account", "string", false],
  ];

  const settings = {};

  for (const [name, type, isPublic] of fields) {
    if (!formData.has(name)) {
      continue;
    }

    const raw = formData.get(name);

    settings[name] = {
      value: type === "boolean" ? raw === "on" || raw === "1" : raw,
      type,
      group: name.split(".")[0],
      is_public: isPublic,
    };
  }

  try {
    await saveSettings(token, settings);
  } catch (error) {
    return toActionError(error, "Settings could not be saved.");
  }

  revalidateTag("settings", "max");
  revalidateTag("insights", "max");
  revalidatePath("/", "layout");
  revalidatePath("/admin/insights");

  return { success: "Settings saved." };
}

/*
|--------------------------------------------------------------------------
| Backups
|--------------------------------------------------------------------------
*/

export async function createBackupAction(prevState, formData) {
  const token = await getToken();

  if (!token) {
    return { error: "Your session expired. Sign in again." };
  }

  const kind = String(formData.get("kind") || "database");

  try {
    const payload = await createBackup(token, {
      kind,
      note: String(formData.get("note") || "") || null,
    });

    revalidatePath("/admin/backups");

    return { success: `Backup created: ${payload?.data?.filename || kind}.` };
  } catch (error) {
    return toActionError(error, "The backup could not be created.");
  }
}

export async function restoreBackupAction(formData) {
  const token = await getToken();
  const id = formData.get("id");

  if (!token || !id) {
    return;
  }

  await restoreBackup(token, id).catch(() => null);
  revalidatePath("/admin/backups");
  revalidatePath("/admin");
  revalidatePath("/", "layout");
}

export async function deleteBackupAction(formData) {
  const token = await getToken();
  const id = formData.get("id");

  if (!token || !id) {
    return;
  }

  await deleteBackup(token, id).catch(() => null);
  revalidatePath("/admin/backups");
}

/*
|--------------------------------------------------------------------------
| Navigation, widgets and appearance
|--------------------------------------------------------------------------
*/

function invalidateChrome() {
  revalidateTag("menus", "max");
  revalidateTag("widgets", "max");
  revalidatePath("/", "layout");
}

export async function createMenuAction(prevState, formData) {
  const token = await getToken();

  if (!token) {
    return { error: "Your session expired. Sign in again." };
  }

  const location = String(formData.get("location") || "");
  let created;

  try {
    created = await createMenu(token, {
      name: String(formData.get("name") || ""),
      location: location || null,
      description: String(formData.get("description") || "") || null,
    });
  } catch (error) {
    return toActionError(error, "The menu could not be created.");
  }

  invalidateChrome();
  redirect(`/admin/menus?menu=${created?.data?.id || ""}`);
}

export async function updateMenuAction(prevState, formData) {
  const token = await getToken();
  const id = formData.get("id");

  if (!token || !id) {
    return { error: "Your session expired. Sign in again." };
  }

  const location = String(formData.get("location") || "");

  try {
    await updateMenu(token, id, {
      name: String(formData.get("name") || ""),
      location: location || null,
      description: String(formData.get("description") || "") || null,
    });
  } catch (error) {
    return toActionError(error, "The menu could not be saved.");
  }

  invalidateChrome();
  return { success: "Menu saved." };
}

export async function deleteMenuAction(formData) {
  const token = await getToken();
  const id = formData.get("id");

  if (!token || !id) {
    return;
  }

  await deleteMenu(token, id);
  invalidateChrome();
  redirect("/admin/menus");
}

export async function saveMenuItemAction(prevState, formData) {
  const token = await getToken();
  const menuId = formData.get("menu_id");
  const itemId = formData.get("item_id");

  if (!token || !menuId) {
    return { error: "Your session expired. Sign in again." };
  }

  const type = String(formData.get("type") || "custom");
  const linkable = formData.get("linkable_id");

  const payload = {
    label: String(formData.get("label") || ""),
    type,
    url: type === "custom" ? String(formData.get("url") || "") : null,
    linkable_id: type === "custom" || !linkable ? null : Number(linkable),
    parent_id: formData.get("parent_id") ? Number(formData.get("parent_id")) : null,
    target: String(formData.get("target") || "_self"),
    sort_order: Number(formData.get("sort_order") || 0),
    is_active: formData.get("is_active") === "on",
  };

  try {
    if (itemId) {
      await updateMenuItem(token, itemId, payload);
    } else {
      await createMenuItem(token, menuId, payload);
    }
  } catch (error) {
    return toActionError(error, "The menu item could not be saved.");
  }

  invalidateChrome();
  revalidatePath("/admin/menus");

  return { success: itemId ? "Menu item saved." : "Menu item added." };
}

export async function deleteMenuItemAction(formData) {
  const token = await getToken();
  const id = formData.get("id");

  if (!token || !id) {
    return;
  }

  await deleteMenuItem(token, id);
  invalidateChrome();
  revalidatePath("/admin/menus");
}

export async function reorderMenuItemsAction(menuId, items) {
  const token = await getToken();

  if (!token) {
    throw new Error("Your session expired. Sign in again.");
  }

  try {
    await reorderMenuItems(token, menuId, { items });
  } catch (error) {
    throw new Error(toActionMessage(error, "The new order could not be saved."));
  }

  invalidateChrome();
  revalidatePath("/admin/menus");
}

export async function saveWidgetAction(prevState, formData) {
  const token = await getToken();

  if (!token) {
    return { error: "Your session expired. Sign in again." };
  }

  const id = formData.get("id");
  const type = String(formData.get("type") || "rich_text");
  const menuId = formData.get("menu_id");

  const payload = {
    area: String(formData.get("area") || "sidebar"),
    type,
    title: String(formData.get("title") || "") || null,
    content: String(formData.get("content") || ""),
    menu_id: type === "menu" && menuId ? Number(menuId) : null,
    sort_order: Number(formData.get("sort_order") || 0),
    is_active: formData.get("is_active") === "on",
  };

  try {
    if (id) {
      await updateWidget(token, id, payload);
    } else {
      await createWidget(token, payload);
    }
  } catch (error) {
    return toActionError(error, "The widget could not be saved.");
  }

  invalidateChrome();
  revalidatePath("/admin/widgets");

  return { success: id ? "Widget saved." : "Widget created." };
}

export async function deleteWidgetAction(formData) {
  const token = await getToken();
  const id = formData.get("id");

  if (!token || !id) {
    return;
  }

  await deleteWidget(token, id);
  invalidateChrome();
  revalidatePath("/admin/widgets");
}

export async function reorderWidgetsAction(widgets) {
  const token = await getToken();

  if (!token) {
    throw new Error("Your session expired. Sign in again.");
  }

  try {
    await reorderWidgets(token, { widgets });
  } catch (error) {
    throw new Error(toActionMessage(error, "The new order could not be saved."));
  }

  invalidateChrome();
  revalidatePath("/admin/widgets");
}

export async function saveThemeAction(prevState, formData) {
  const token = await getToken();

  if (!token) {
    return { error: "Your session expired. Sign in again." };
  }

  try {
    await saveTheme(token, {
      theme: String(formData.get("theme") || "default"),
      accent: String(formData.get("accent") || "") || null,
    });
  } catch (error) {
    return toActionError(error, "The theme could not be changed.");
  }

  revalidateTag("settings", "max");
  revalidatePath("/", "layout");

  return { success: "Theme updated." };
}

/*
|--------------------------------------------------------------------------
| Users
|--------------------------------------------------------------------------
*/

export async function createUserAction(prevState, formData) {
  const token = await getToken();

  if (!token) {
    return { error: "Your session expired. Sign in again." };
  }

  try {
    await createUser(token, {
      name: String(formData.get("name") || ""),
      email: String(formData.get("email") || ""),
      password: String(formData.get("password") || ""),
      roles: [String(formData.get("role") || "subscriber")],
    });
  } catch (error) {
    return toActionError(error, "The user could not be created.");
  }

  revalidatePath("/admin/users");

  return { success: "User created." };
}
