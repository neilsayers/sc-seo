<?php

namespace SCSEO\Frontend;

use SCSEO\Contracts\Hookable;
use SCSEO\Support\RedirectCache;
use SCSEO\Support\RedirectMeta;

/**
 * Matches every front-end request's path against Support\RedirectCache
 * before WordPress gets a chance to 404 it. Runs on template_redirect
 * — late enough that a real page/post at that path (which should just
 * load normally) has already been resolved, early enough that it
 * fires before the 404 template would otherwise render.
 */
final class RedirectHandler implements Hookable
{
    public function register(): void
    {
        \add_action('template_redirect', [$this, 'maybeRedirect'], 0);
    }

    public function maybeRedirect(): void
    {
        $path = \wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), \PHP_URL_PATH) ?: '/';

        // Strip the site's own subdirectory (if installed in one) so
        // redirects are always authored relative to the site root.
        $homePath = \wp_parse_url(\home_url('/'), \PHP_URL_PATH) ?: '/';

        if ($homePath !== '/' && \str_starts_with($path, $homePath)) {
            $path = \substr($path, \strlen(\rtrim($homePath, '/')));
        }

        $redirect = RedirectCache::find(RedirectCache::normalize($path));

        if ($redirect === null) {
            return;
        }

        RedirectMeta::recordHit($redirect['post_id']);

        if (\in_array($redirect['type'], ['410', '451'], true)) {
            \status_header((int) $redirect['type']);
            \nocache_headers();

            exit;
        }

        // wp_safe_redirect() would silently downgrade a redirect to an
        // external destination (a merged/renamed domain, a moved
        // resource) to the site's own home URL — wp_redirect() sends
        // exactly the target that was configured.
        \wp_redirect($redirect['target'], (int) $redirect['type']);

        exit;
    }
}
