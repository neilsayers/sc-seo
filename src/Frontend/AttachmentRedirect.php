<?php

namespace SCSEO\Frontend;

use SCSEO\Contracts\Hookable;
use SCSEO\Settings\Settings;

/**
 * Sends a visit to an attachment's own page — image + title, nothing
 * else — on to wherever that attachment actually belongs, instead of
 * letting WordPress serve the standalone page it generates for every
 * upload. Gated on Settings::get('redirect_attachment_pages') — see
 * Admin\SettingsPage for why this is a per-site opt-in rather than
 * baked-in behaviour.
 */
final class AttachmentRedirect implements Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('template_redirect', [$this, 'maybeRedirect'], 0);
    }

    public function maybeRedirect(): void
    {
        if (! $this->settings->get('redirect_attachment_pages', false) || ! \is_attachment()) {
            return;
        }

        $attachment = \get_queried_object();
        $parentId = $attachment instanceof \WP_Post ? (int) $attachment->post_parent : 0;
        $target = $parentId ? \get_permalink($parentId) : false;

        \wp_safe_redirect($target ?: \home_url('/'), 301);

        exit;
    }
}
