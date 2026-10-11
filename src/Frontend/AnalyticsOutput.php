<?php

namespace SCSEO\Frontend;

use SCSEO\Contracts\Hookable;
use SCSEO\Network\NetworkSettings;
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
    public function __construct(private Settings $settings, private NetworkSettings $network)
    {
    }

    public function register(): void
    {
        \add_action('wp_head', [$this, 'renderTag'], 1);
    }

    public function renderTag(): void
    {
        $siteId = (string) $this->settings->get('ga_measurement_id');
        $networkId = $this->network->gaMeasurementId();

        if ($siteId === '' && $networkId === '') {
            return;
        }

        // Sitewide noindex is this plugin's own "this isn't the real
        // site" switch (staging, a pre-launch clone) — skip sending
        // that traffic to GA rather than needing a second flag for
        // the same thing.
        if ($this->settings->get('default_robots_noindex')) {
            return;
        }

        // The site's own property (if it has one) and the network-wide
        // one each get the hit; the first ID also loads gtag.js.
        $configs = [];

        if ($siteId !== '') {
            $configs[] = \sprintf('gtag("config","%s");', \esc_js($siteId));
        }

        if ($networkId !== '' && $networkId !== $siteId) {
            // site_id/site_host let the network property tell subsites
            // apart once they're on their own domains. They only show
            // up in GA reports after being registered there as
            // event-scoped custom dimensions.
            $configs[] = \sprintf(
                'gtag("config","%s",{site_id:%d,site_host:"%s"});',
                \esc_js($networkId),
                \get_current_blog_id(),
                \esc_js((string) \wp_parse_url(\home_url(), PHP_URL_HOST))
            );
        }

        printf(
            '<script async src="https://www.googletagmanager.com/gtag/js?id=%1$s"></script>'."\n".
            '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());%2$s</script>'."\n",
            \esc_attr($siteId !== '' ? $siteId : $networkId),
            \implode('', $configs)
        );
    }
}
