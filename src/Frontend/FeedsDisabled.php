<?php

namespace SCSEO\Frontend;

use SCSEO\Contracts\Hookable;
use SCSEO\Settings\Settings;

/**
 * Closes every feed WordPress generates by default — the main site
 * feed plus a separate one per tag, per author, per comment thread and
 * for search — for a site with no reader, newsletter tool or
 * subscriber that reads any of them. Gated on
 * Settings::get('disable_feeds'): another site sharing this plugin may
 * still have real subscribers, so this is a per-site opt-in rather
 * than something the plugin decides for everyone.
 *
 * The <link rel="alternate"> discovery tags are removed in register()
 * rather than inside a callback, unlike this plugin's other toggles —
 * remove_action() is one-time wiring, not something with a per-request
 * value to branch on, so the setting is read once, up front, instead.
 */
final class FeedsDisabled implements Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        if (! $this->settings->get('disable_feeds', false)) {
            return;
        }

        \remove_action('wp_head', 'feed_links', 2);
        \remove_action('wp_head', 'feed_links_extra', 3);

        \add_action('template_redirect', [$this, 'block'], 1);
    }

    public function block(): void
    {
        if (! \is_feed()) {
            return;
        }

        global $wp_query;

        $wp_query->set_404();

        \status_header(404);
        \nocache_headers();
    }
}
