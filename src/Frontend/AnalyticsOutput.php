<?php

namespace SCSEO\Frontend;

use SCSEO\Contracts\Hookable;
use SCSEO\Settings\Settings;

/**
 * The GA4 gtag.js loader — one field (SC SEO → General), one snippet,
 * printed only when a Measurement ID is actually set. Deliberately not
 * a general-purpose "paste any tracking script here" box: that's a
 * much larger surface (consent, CSP, script ordering) than this
 * plugin's own "no bloat" premise wants to take on. If a site needs
 * more than GA someday, that's a Site Kit-shaped job, not this one.
 */
final class AnalyticsOutput implements Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('wp_head', [$this, 'renderTag'], 1);
    }

    public function renderTag(): void
    {
        $id = (string) $this->settings->get('ga_measurement_id');

        if ($id === '') {
            return;
        }

        // Sitewide noindex is this plugin's own "this isn't the real
        // site" switch (staging, a pre-launch clone) — skip sending
        // that traffic to GA rather than needing a second flag for
        // the same thing.
        if ($this->settings->get('default_robots_noindex')) {
            return;
        }

        printf(
            '<script async src="https://www.googletagmanager.com/gtag/js?id=%1$s"></script>'."\n".
            '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","%1$s");</script>'."\n",
            \esc_attr($id)
        );
    }
}
