<?php

namespace SCSEO\Support;

/**
 * Reads/writes every _scseo_* field for a single redirect post. The
 * post's own title is the source path (see PostTypes\RedirectPostType)
 * — this class only owns the fields that aren't already a native post
 * field. Shared by Admin\RedirectsPage (writes/lists them) and
 * Support\RedirectCache (reads them to build the lookup array).
 */
final class RedirectMeta
{
    public const TYPES = [
        '301' => '301 — Moved Permanently',
        '302' => '302 — Found (temporary)',
        '410' => '410 — Gone',
        '451' => '451 — Unavailable for Legal Reasons',
    ];

    /**
     * @return array{target: string, type: string, hits: int, last_hit: string}
     */
    public static function read(int $postId): array
    {
        return [
            'target' => (string) \get_post_meta($postId, '_scseo_target', true),
            'type' => (string) \get_post_meta($postId, '_scseo_type', true) ?: '301',
            'hits' => (int) \get_post_meta($postId, '_scseo_hits', true),
            'last_hit' => (string) \get_post_meta($postId, '_scseo_last_hit', true),
        ];
    }

    public static function save(int $postId, string $target, string $type): void
    {
        MetaField::saveValue($postId, '_scseo_target', \esc_url_raw($target));
        MetaField::saveValue($postId, '_scseo_type', \array_key_exists($type, self::TYPES) ? $type : '301');
    }

    /**
     * Called from the redirect hot path (Frontend\RedirectHandler)
     * after the redirect itself has already been sent — never blocks
     * the response, since headers_sent() by this point is irrelevant
     * to a 3xx that's already gone out.
     */
    public static function recordHit(int $postId): void
    {
        \update_post_meta($postId, '_scseo_hits', ((int) \get_post_meta($postId, '_scseo_hits', true)) + 1);
        \update_post_meta($postId, '_scseo_last_hit', \current_time('mysql'));
    }
}
