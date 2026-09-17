<?php

namespace SCSEO\Support;

use SCSEO\PostTypes\RedirectPostType;

/**
 * A normalized-path => [target, type, post_id] lookup, cached in one
 * option row rather than queried live. Frontend\RedirectHandler runs
 * on every single front-end request (it has to, to catch old-site
 * URLs before WordPress 404s them), so that path reads this cache
 * only — one autoloaded option, not a posts+postmeta query per
 * request. Rebuilt whenever a redirect is added/edited/deleted/
 * toggled (Admin\RedirectsPage) and on plugin activation.
 */
final class RedirectCache
{
    private const OPTION_KEY = 'scseo_redirect_cache';

    /**
     * @return array<string, array{target: string, type: string, post_id: int}>
     */
    public static function all(): array
    {
        $cache = \get_option(self::OPTION_KEY, []);

        return \is_array($cache) ? $cache : [];
    }

    /**
     * @return array{target: string, type: string, post_id: int}|null
     */
    public static function find(string $normalizedSource): ?array
    {
        return self::all()[$normalizedSource] ?? null;
    }

    /**
     * Only published redirects are live — Admin\RedirectsPage uses
     * draft status as the "inactive" toggle, so a paused redirect
     * simply never makes it into this cache.
     */
    public static function rebuild(): void
    {
        $posts = \get_posts([
            'post_type' => RedirectPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);

        $cache = [];

        foreach ($posts as $post) {
            $meta = RedirectMeta::read($post->ID);

            if ($meta['target'] === '') {
                continue;
            }

            $cache[$post->post_title] = [
                'target' => $meta['target'],
                'type' => $meta['type'],
                'post_id' => $post->ID,
            ];
        }

        \update_option(self::OPTION_KEY, $cache);
    }

    /**
     * The same normalization used both when a redirect is saved (its
     * post_title, the source path, is stored already-normalized) and
     * when an incoming request is matched against this cache — the
     * two must always agree or nothing would ever match.
     */
    public static function normalize(string $path): string
    {
        $path = \wp_parse_url($path, \PHP_URL_PATH) ?? $path;
        $path = '/'.\ltrim($path, '/');
        $path = \rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }
}
