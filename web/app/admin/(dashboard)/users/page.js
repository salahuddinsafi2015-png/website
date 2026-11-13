export const instant = false;

import UserForm from "@/components/admin/UserForm";
import { getUsers } from "@/lib/admin-api";
import { requireToken } from "@/lib/session";

export default async function UsersPage() {
  const token = await requireToken();

  let payload;

  try {
    payload = await getUsers(token, { per_page: 50 });
  } catch {
    return (
      <p className="rounded-lg border border-border-subtle p-4 text-sm">
        User management is limited to administrators.
      </p>
    );
  }

  const users = payload?.data || [];

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Users</h1>
        <p className="mt-1 text-sm text-muted">
          Roles control what each person can write, publish and moderate.
        </p>
      </header>

      <UserForm />

      <div className="overflow-x-auto rounded-xl border border-border-subtle">
        <table className="w-full min-w-[520px] text-left text-sm">
          <thead className="bg-surface/60 text-xs uppercase tracking-wide text-muted">
            <tr>
              <th className="px-4 py-3 font-medium">Name</th>
              <th className="px-4 py-3 font-medium">Username</th>
              <th className="px-4 py-3 font-medium">Roles</th>
              <th className="px-4 py-3 font-medium">Joined</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border-subtle">
            {users.map((user) => (
              <tr key={user.id}>
                <td className="px-4 py-3">
                  <p className="font-medium">{user.display_name || user.name}</p>
                  <p className="text-xs text-muted">{user.email}</p>
                </td>
                <td className="px-4 py-3">{user.username}</td>
                <td className="px-4 py-3">{(user.roles || []).join(", ") || "—"}</td>
                <td className="px-4 py-3 text-muted">
                  {new Date(user.created_at).toLocaleDateString("en-US")}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}