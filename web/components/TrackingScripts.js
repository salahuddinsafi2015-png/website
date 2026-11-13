// Only well-formed Google IDs are injected; anything else is ignored.
const GA_ID = /^G-[A-Z0-9]{4,20}$/i;
const ADSENSE_ID = /^ca-pub-\d{10,20}$/;

/**
 * Loads Google Analytics 4 and AdSense on public pages only.
 *
 * The scripts are injected by a tiny inline bootstrap rather than <Script>,
 * because a client component reading the pathname would opt every public page
 * out of static rendering. Admin sessions are skipped so they never pollute
 * audience reports or ad performance.
 */
export default function TrackingScripts({ measurementId, adsenseClientId }) {
  const ga = GA_ID.test(measurementId || "") ? measurementId : null;
  const ads = ADSENSE_ID.test(adsenseClientId || "") ? adsenseClientId : null;

  if (!ga && !ads) {
    return null;
  }

  const bootstrap = [
    "(function(){",
    "if(location.pathname.indexOf('/admin')===0||location.pathname.indexOf('/api')===0)return;",
    ads
      ? `var a=document.createElement('script');a.async=true;a.crossOrigin='anonymous';` +
        `a.src='https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${ads}';` +
        "document.head.appendChild(a);"
      : "",
    ga
      ? "window.dataLayer=window.dataLayer||[];" +
        "window.gtag=function(){window.dataLayer.push(arguments);};" +
        "gtag('js',new Date());" +
        `gtag('config',${JSON.stringify(ga)});` +
        "var g=document.createElement('script');g.async=true;" +
        `g.src='https://www.googletagmanager.com/gtag/js?id=${ga}';` +
        "document.head.appendChild(g);"
      : "",
    "})();",
  ].join("");

  return <script id="cms-tracking" dangerouslySetInnerHTML={{ __html: bootstrap }} />;
}
