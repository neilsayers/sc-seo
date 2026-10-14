=== SC SEO ===
Contributors: screencandy
Tags: seo, open graph, schema, structured data, redirects
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lean, site-agnostic technical SEO — titles, meta descriptions, Open Graph/Twitter cards, canonical URLs, robots
controls, schema.org JSON-LD, and a redirect manager. No content-analysis bloat.

== Description ==

SC SEO is built to be dropped into any WordPress site as-is — no build step, no Composer install. It deliberately
covers only the technical side of SEO (the part search engines and social platforms actually read from markup) and
leaves out the content-analysis/readability scoring that plugins like Yoast or Rank Math build their UI around.

Every public post type gets an "SC SEO" box above the content editor: an SEO title and meta description (with a
live SERP-style preview and a character counter, not a quality score), a canonical URL override, noindex/nofollow
checkboxes, social title/description/image overrides, and a structured-data type choice. Any field left blank falls
back sensibly — title/description to a sitewide template, social image to the featured image, then a sitewide
default.

One JSON-LD "@graph" block is printed on every page: an Organization (or LocalBusiness/ProfessionalService/Store/
Restaurant — SC SEO → Settings → Business & Schema) node plus a WebSite node — the part that actually helps a
Knowledge Panel or an AI answer engine identify who the site belongs to — and, on singular content, a BreadcrumbList
plus a page-level Article/WebPage/FAQPage node. The whole graph is filterable via `scseo_schema_graph`, so another
plugin (SC Events Manager, SC Room Bookings, or this site's own theme) can add its own nodes without SC SEO needing
to know it exists.

No custom XML sitemap — WordPress core has served one at /wp-sitemap.xml since 5.5. SC SEO only makes sure a post
marked noindex here is also excluded from that.

== Redirects ==

SC SEO → Redirects manages 301/302/410/451 redirects, either one at a time or via a CSV bulk import (`source,
destination,type` — type optional, defaults to 301) — built for migrating a retired site's URLs in one go without
losing their link equity. Each redirect is a `scseo_redirect` post under the hood (source path as the title, target/
type/hit-count as postmeta), but the front-end match against every incoming request is served from a single cached
lookup array (rebuilt whenever a redirect changes), not a live database query per request.

== Changelog ==

= 0.4.0 =
* Cookie consent for Google Analytics. With a Measurement ID set, a small Accept / Reject banner appears and gtag.js is not
  requested at all until the visitor accepts (no cookies, nothing sent to Google beforehand). The choice is remembered in
  the browser for 12 months. On by default; SC SEO -> General -> Cookie consent turns it off or changes the wording.
* The banner is a switch: turn it off under SC SEO -> General -> Cookie consent if another plugin handles consent. While
  Google Analytics is active and the switch is off, a warning shows on the settings page, Dashboard and Plugins screens.
* Plain, zero-specificity styling (`:where()`) with BEM classes, state classes on `<html>`, a `scseo:consent` event and
  `--scseo-consent-*` custom properties for theming; `scseo_consent_enqueue_css` filter drops the stylesheet. See Documentation.
* `[scseo_cookie_settings]` shortcode (or `data-scseo-consent-open` on any element) lets visitors reopen the banner.

= 0.3.2 =
* The Post types setting no longer lists non-public post types; a note explains that only public ones appear.

= 0.3.1 =
* The Post types setting now also lists post types that have an editor but no public pages (shown greyed out, marked
  "not public"), with a note that only public post types can be switched on.

= 0.3.0 =
* Multisite: new Network tab (main site, super admins only) with a master switch, off by default. When on, every
  subsite also reports to one network-wide GA4 property, tagged with site_id and site_host so reports can be split
  per site. A subsite's own Measurement ID keeps working alongside it. With the switch off nothing changes.
* New "Post types" setting (SC SEO → General): hide the SEO box from the editor of any post type, and leave any post
  type out of the XML sitemap. Both default to on, including for post types registered later.

= 0.2.3 =
* Sites running this plugin now get the normal "Update available" notice in wp-admin, served from this plugin's GitHub Releases.

= 0.2.2 =
* Added three opt-in settings (all off by default, so existing sites behave as before): noindex thin archives (single-post
  tag archives, the sole author's archive, date archives), redirect attachment pages to their parent, and disable
  unused RSS/Atom feeds.
* The posts page (blog index) is now treated as a real page: it gets its own title, canonical URL, meta description and
  Open Graph tags, where before it had no canonical or description.

= 0.2.1 =
* Fixed the XML sitemap noindex filter excluding posts with a stray, falsy `_scseo_noindex` postmeta row (e.g. left
  over from an import) — those were silently dropped from the sitemap even though nothing on the page itself said
  noindex. The filter now correctly keeps any post where the meta doesn't exist or isn't truthy.

= 0.2.0 =
* Added an optional GA4 Measurement ID field (SC SEO → Settings → General) — prints the gtag.js snippet only when
  set, and is skipped automatically whenever the sitewide "discourage search engines" noindex switch is on, so
  staging/pre-launch traffic never reaches a real property.
* Post types edited in the block editor (use_block_editor_for_post_type()) now get a native Gutenberg
  PluginDocumentSettingPanel — the same fields as the classic "SC SEO" box, reading/writing the same postmeta (now
  registered as REST-visible via register_post_meta()) — instead of the classic box, which only ever showed for
  classic-editor post types to begin with. No build step: hand-written against the wp-* globals the block editor
  already loads, matching every other SC plugin's own JS.

= 0.1.0 =
* Initial release.
