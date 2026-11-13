import Link from "next/link";
import { Geist, Geist_Mono } from "next/font/google";

import { SiteSchema } from "@/components/StructuredData";
import TrackingScripts from "@/components/TrackingScripts";
import WidgetArea from "@/components/WidgetArea";
import { getMenu, getSettings, getTerms } from "@/lib/content";

import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || "http://localhost:3000";

// Only registered palettes may reach the DOM attribute.
const THEMES = ["default", "editorial", "midnight"];

export async function generateMetadata() {
  const settings = await getSettings();
  const name = settings["site.name"] || "Lumen CMS";
  const description =
    settings["site.description"] ||
    "A WordPress-style CMS powered by Laravel and Next.js.";

  return {
    metadataBase: new URL(SITE_URL),
    title: {
      default: name,
      template: `%s · ${name}`,
    },
    description,
    openGraph: {
      type: "website",
      siteName: name,
      url: SITE_URL,
      title: name,
      description,
    },
    twitter: {
      card: "summary_large_image",
      title: name,
      description,
    },
    // No canonical here: a single root canonical would claim every page is
    // the homepage. Each route declares its own absolute canonical instead.
    alternates: {
      types: {
        "application/rss+xml": "/feed.xml",
      },
    },
    robots: {
      index: true,
      follow: true,
      googleBot: {
        index: true,
        follow: true,
        "max-image-preview": "large",
        "max-snippet": -1,
        "max-video-preview": -1,
      },
    },
  };
}

async function SiteHeader() {
  const [settings, menu, categories] = await Promise.all([
    getSettings(),
    getMenu("primary"),
    getTerms("category"),
  ]);

  const name = settings["site.name"] || "Lumen CMS";
  const menuItems = (menu?.items || []).filter((item) => item.url);

  return (
    <header className="border-b border-border-subtle bg-background/95 backdrop-blur">
      <div className="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-4 sm:px-6 md:flex-row md:items-center md:justify-between">
        <Link href="/" className="text-lg font-semibold tracking-tight">
          {name}
        </Link>
        <nav aria-label="Primary" className="flex flex-wrap items-center gap-4 text-sm">
          {menuItems.length > 0
            ? menuItems.map((item) => (
                <Link
                  key={item.id}
                  className="hover:text-accent"
                  href={item.url}
                  target={item.target}
                  rel={item.rel || undefined}
                >
                  {item.label}
                </Link>
              ))
            : categories.slice(0, 4).map((category) => (
                <Link
                  key={category.id}
                  className="hover:text-accent"
                  href={`/categories/${category.slug}`}
                >
                  {category.name}
                </Link>
              ))}
          <Link
            className="rounded-md bg-accent px-3 py-1.5 font-medium text-accent-contrast"
            href="/admin"
          >
            Dashboard
          </Link>
        </nav>
      </div>
    </header>
  );
}

async function SiteFooter() {
  return (
    <footer className="border-t border-border-subtle">
      <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-[minmax(0,1fr)_18rem]">
        <div className="flex flex-col gap-2 text-sm text-muted">
          <p>Published with Lumen CMS — Laravel and Next.js.</p>
          <div className="flex gap-4">
            <Link className="hover:text-accent" href="/feed.xml">
              RSS
            </Link>
            <Link className="hover:text-accent" href="/posts">
              All posts
            </Link>
            <Link className="hover:text-accent" href="/admin">
              Admin
            </Link>
          </div>
        </div>
        <WidgetArea area="footer" />
      </div>
    </footer>
  );
}

export default async function RootLayout({ children }) {
  const settings = await getSettings();
  const siteName = settings["site.name"] || "Lumen CMS";
  const siteDescription =
    settings["site.description"] ||
    "A WordPress-style CMS powered by Laravel and Next.js.";
  const theme = THEMES.includes(settings["appearance.theme"])
    ? settings["appearance.theme"]
    : "default";
  const accent = /^#[0-9a-fA-F]{6}$/.test(settings["appearance.accent"] || "")
    ? settings["appearance.accent"]
    : undefined;

  return (
    <html
      lang="en"
      data-theme={theme}
      style={accent ? { "--accent": accent } : undefined}
      className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}
    >
      <body className="flex min-h-full flex-col">
        <a
          href="#main"
          className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-accent focus:px-3 focus:py-2 focus:text-accent-contrast"
        >
          Skip to content
        </a>
        <SiteHeader />
        <main id="main" className="flex-1">
          {children}
        </main>
        <SiteFooter />
        <SiteSchema
          siteName={siteName}
          siteUrl={SITE_URL}
          description={siteDescription}
        />
        <TrackingScripts
          measurementId={settings["analytics.measurement_id"]}
          adsenseClientId={settings["adsense.client_id"]}
        />
      </body>
    </html>
  );
}
