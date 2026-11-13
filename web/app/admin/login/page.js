import { redirect } from "next/navigation";

import LoginForm from "@/components/LoginForm";
import { getToken } from "@/lib/session";

export const metadata = {
  title: "Sign in",
  robots: { index: false, follow: false },
};

export const instant = false;

export default async function LoginPage() {
  const token = await getToken();

  if (token) {
    redirect("/admin");
  }

  return (
    <div className="mx-auto flex max-w-md flex-col justify-center px-4 py-20">
      <h1 className="text-2xl font-semibold tracking-tight">Sign in to the dashboard</h1>
      <p className="mt-2 text-sm text-muted">
        Use the administrator account created when the site was seeded.
      </p>
      <div className="mt-8 rounded-xl border border-border-subtle bg-surface/40 p-6">
        <LoginForm />
      </div>
      <p className="mt-4 text-xs text-muted">
        Sessions are stored in an httpOnly cookie; the API token never reaches the
        browser.
      </p>
    </div>
  );
}
