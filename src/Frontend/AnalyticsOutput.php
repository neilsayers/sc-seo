<?php

namespace SCSEO\Frontend;

use SCSEO\Contracts\Hookable;
use SCSEO\Network\NetworkSettings;
use SCSEO\Settings\Settings;

/**
 * The GA4 gtag.js loader — one field (SC SEO → General), one snippet,
 * printed only when a Measurement ID is actually set. Deliberately not
 * a general-purpose "paste any tracking script here" box: that's a
 * much larger surface (CSP, script ordering) than this plugin's own
 * "no bloat" premise wants to take on. If a site needs more than GA
 * someday, that's a Site Kit-shaped job, not this one.
 *
 * Consent: with the banner setting on (the default), gtag.js is not
 * requested at all until the visitor clicks Accept — no cookies, no
 * request to Google. The head script only defines scseoLoadGA(), which
 * assets/js/consent.js (or the stored "granted" choice, checked inline
 * so a returning visitor is counted from the first page view) calls.
 * The banner markup is always printed hidden and revealed by JS, so
 * full-page caches can't serve one visitor's choice to another.
 */
final class AnalyticsOutput implements Hookable
{
    public const STORAGE_KEY = 'scseo_consent';

    public function __construct(private Settings $settings, private NetworkSettings $network)
    {
    }

    public function register(): void
    {
        \add_action('wp_head', [$this, 'renderTag'], 1);
        \add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        \add_action('wp_footer', [$this, 'renderBanner']);
        \add_shortcode('scseo_cookie_settings', [$this, 'cookieSettingsLink']);
    }

    /**
     * The measurement IDs this request should report to — the loader ID
     * first (it's the one in the gtag.js URL), then every gtag("config")
     * call — or null when GA shouldn't run at all.
     *
     * @return array{loader: string, configs: list<string>}|null
     */
    private function analytics(): ?array
    {
        $siteId = (string) $this->settings->get('ga_measurement_id');
        $networkId = $this->network->gaMeasurementId();

        if ($siteId === '' && $networkId === '') {
            return null;
        }

        // Sitewide noindex is this plugin's own "this isn't the real
        // site" switch (staging, a pre-launch clone) — skip sending
        // that traffic to GA rather than needing a second flag for
        // the same thing.
        if ($this->settings->get('default_robots_noindex')) {
            return null;
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

        return [
            'loader' => $siteId !== '' ? $siteId : $networkId,
            'configs' => $configs,
        ];
    }

    private function consentRequired(): bool
    {
        return $this->analytics() !== null && (bool) $this->settings->get('consent_banner');
    }

    public function renderTag(): void
    {
        $ga = $this->analytics();

        if ($ga === null) {
            return;
        }

        $configs = \implode('', $ga['configs']);

        if (! $this->consentRequired()) {
            \printf(
                '<script async src="https://www.googletagmanager.com/gtag/js?id=%1$s"></script>'."\n".
                '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());%2$s</script>'."\n",
                \esc_attr($ga['loader']),
                $configs
            );

            return;
        }

        // Nothing leaves the browser until scseoLoadGA() runs. A stored
        // "granted" choice younger than 12 months runs it straight away.
        \printf(
            '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'.
            'window.scseoLoadGA=function(){if(window.scseoGA){return;}window.scseoGA=1;'.
            'var s=document.createElement("script");s.async=true;s.src="https://www.googletagmanager.com/gtag/js?id=%1$s";document.head.appendChild(s);'.
            'gtag("js",new Date());%2$s};'.
            'try{var c=JSON.parse(localStorage.getItem("%3$s")||"null");if(c&&c.v==="granted"&&Date.now()-c.t<31536e6){window.scseoLoadGA();}}catch(e){}</script>'."\n",
            \esc_js($ga['loader']),
            $configs,
            self::STORAGE_KEY
        );
    }

    public function enqueueAssets(): void
    {
        if (! $this->consentRequired()) {
            return;
        }

        // Return false from this filter to drop the plugin's stylesheet
        // entirely and style the banner from the theme from scratch.
        if (\apply_filters('scseo_consent_enqueue_css', true)) {
            \wp_enqueue_style('scseo-consent', SCSEO_URL.'assets/css/consent.css', [], SCSEO_VERSION);
        }

        \wp_enqueue_script('scseo-consent', SCSEO_URL.'assets/js/consent.js', [], SCSEO_VERSION, true);
        \wp_localize_script('scseo-consent', 'scseoConsent', ['key' => self::STORAGE_KEY]);
    }

    public function renderBanner(): void
    {
        if (! $this->consentRequired()) {
            return;
        }

        $message = (string) $this->settings->get('consent_message');
        $privacyUrl = \get_privacy_policy_url();
        ?>
        <div id="scseo-consent" class="scseo-consent" data-scseo-consent-state="unset" role="dialog" aria-label="Cookie consent" hidden>
            <div class="scseo-consent__inner">
                <p class="scseo-consent__text">
                    <span class="scseo-consent__message"><?php echo \esc_html($message !== '' ? $message : Settings::DEFAULT_CONSENT_MESSAGE); ?></span>
                    <?php if ($privacyUrl !== '') : ?>
                        <a class="scseo-consent__link" href="<?php echo \esc_url($privacyUrl); ?>">Privacy policy</a>
                    <?php endif; ?>
                </p>
                <div class="scseo-consent__actions">
                    <button type="button" class="scseo-consent__btn scseo-consent__btn--reject" data-scseo-consent="denied">Reject</button>
                    <button type="button" class="scseo-consent__btn scseo-consent__btn--accept" data-scseo-consent="granted">Accept</button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * [scseo_cookie_settings] — a button that re-opens the banner, for
     * the privacy policy page or a footer. Prints nothing when there is
     * no banner to re-open.
     */
    public function cookieSettingsLink(): string
    {
        if (! $this->consentRequired()) {
            return '';
        }

        return '<button type="button" class="scseo-consent-reopen" data-scseo-consent-open>Cookie settings</button>';
    }
}
