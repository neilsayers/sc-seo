<?php

namespace SCSEO\Admin;

use SCSEO\Contracts\Hookable;

/**
 * "Documentation" — a plain reference for the per-post meta keys,
 * the schema filter hook, and the redirect CSV format. Same shape as
 * SC Maps/SC Events Manager/SC Room Bookings' own DocumentationPage.
 */
final class DocumentationPage implements Hookable
{
    public function register(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        \add_submenu_page(
            SettingsPage::PAGE_SLUG,
            'Documentation',
            'Documentation',
            'manage_options',
            'scseo-documentation',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (! \current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>SC SEO — Documentation</h1>

            <h2>Extending the structured data</h2>
            <p>
                Every page's JSON-LD is assembled as one array of nodes and passed through a filter before it's
                printed — this is the point where another plugin (or this site's own theme) can add its own nodes
                without SC SEO needing to know anything about it:
            </p>
            <pre>add_filter('scseo_schema_graph', function (array $graph, ?WP_Post $post) {
    if ($post && $post->post_type === 'my_event_type') {
        $graph[] = [
            '@type' => 'Event',
            'name' => get_the_title($post),
            // ...
        ];
    }

    return $graph;
}, 10, 2);</pre>
            <p><code>$post</code> is the queried post on a singular page, <code>null</code> everywhere else (the Organization/WebSite nodes are added on every page).</p>

            <h2>Per-post fields (postmeta)</h2>
            <table class="widefat striped" style="max-width: 700px;">
                <thead><tr><th>Meta key</th><th>What it holds</th></tr></thead>
                <tbody>
                    <tr><td><code>_scseo_title</code></td><td>SEO title override</td></tr>
                    <tr><td><code>_scseo_description</code></td><td>Meta description override</td></tr>
                    <tr><td><code>_scseo_canonical</code></td><td>Canonical URL override</td></tr>
                    <tr><td><code>_scseo_noindex</code> / <code>_scseo_nofollow</code></td><td>Robots overrides ('1' or absent)</td></tr>
                    <tr><td><code>_scseo_og_title</code> / <code>_scseo_og_description</code></td><td>Social title/description overrides</td></tr>
                    <tr><td><code>_scseo_og_image</code></td><td>Social image attachment ID</td></tr>
                    <tr><td><code>_scseo_schema_type</code></td><td>Article / WebPage / FAQPage / none (empty = automatic)</td></tr>
                </tbody>
            </table>

            <h2>Adding SC SEO to a non-public post type</h2>
            <pre>add_filter('scseo_metabox_post_types', function (array $types) {
    $types[] = 'my_non_public_type';

    return $types;
});</pre>

            <h2>Redirects CSV format</h2>
            <p>Three columns, header row optional:</p>
            <pre>source,destination,type
/old-page,/new-page,301
/old-brochure.pdf,https://example.com/downloads/brochure.pdf,301
/discontinued-product,,410</pre>
            <p>
                <code>type</code> is optional and defaults to 301. Existing sources are always skipped, never
                overwritten — re-running the same file twice is safe.
            </p>
        </div>
        <?php
    }
}
