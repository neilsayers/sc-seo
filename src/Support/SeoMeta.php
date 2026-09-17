<?php

namespace SCSEO\Support;

/**
 * Reads/writes every _scseo_* field a single post/page/CPT entry can
 * override — everything MetaBoxes\SeoMetaBox shows, and everything
 * Frontend\HeadOutput/SchemaOutput fall back from. One definition of
 * what a post's SEO overrides look like, shared by both.
 */
final class SeoMeta
{
    public const SCHEMA_TYPES = [
        'auto' => 'Automatic (recommended)',
        'Article' => 'Article',
        'WebPage' => 'Web Page',
        'FAQPage' => 'FAQ Page',
        'none' => 'None (no page-level schema)',
    ];

    /**
     * Every public post type gets SC SEO's fields by default —
     * filterable so a site can add a non-public CPT or drop one it
     * doesn't want SEO fields on, e.g.
     * add_filter('scseo_metabox_post_types', ...). Shared by the
     * classic metabox, the REST meta registration, and the Gutenberg
     * sidebar panel — MetaBoxes\SeoMetaBox picks the classic-editor
     * subset, MetaBoxes\SeoSidebarPanel the block-editor subset, but
     * both start from this one list.
     *
     * @return string[]
     */
    public static function eligiblePostTypes(): array
    {
        $types = \get_post_types(['public' => true, 'show_ui' => true], 'names');

        return \apply_filters('scseo_metabox_post_types', \array_values($types));
    }

    /**
     * @return array{title: string, description: string, canonical: string, noindex: bool, nofollow: bool, og_title: string, og_description: string, og_image: int, schema_type: string}
     */
    public static function read(int $postId): array
    {
        return [
            'title' => (string) \get_post_meta($postId, '_scseo_title', true),
            'description' => (string) \get_post_meta($postId, '_scseo_description', true),
            'canonical' => (string) \get_post_meta($postId, '_scseo_canonical', true),
            'noindex' => (bool) \get_post_meta($postId, '_scseo_noindex', true),
            'nofollow' => (bool) \get_post_meta($postId, '_scseo_nofollow', true),
            'og_title' => (string) \get_post_meta($postId, '_scseo_og_title', true),
            'og_description' => (string) \get_post_meta($postId, '_scseo_og_description', true),
            'og_image' => (int) \get_post_meta($postId, '_scseo_og_image', true),
            'schema_type' => (string) \get_post_meta($postId, '_scseo_schema_type', true) ?: 'auto',
        ];
    }

    /**
     * @param array<string, mixed> $data The "scseo" POST sub-array.
     */
    public static function save(int $postId, array $data): void
    {
        MetaField::saveText($postId, '_scseo_title', (string) ($data['title'] ?? ''));
        MetaField::saveText($postId, '_scseo_description', (string) ($data['description'] ?? ''));
        MetaField::saveValue($postId, '_scseo_canonical', \esc_url_raw((string) ($data['canonical'] ?? '')));
        MetaField::saveValue($postId, '_scseo_noindex', ! empty($data['noindex']) ? '1' : '');
        MetaField::saveValue($postId, '_scseo_nofollow', ! empty($data['nofollow']) ? '1' : '');
        MetaField::saveText($postId, '_scseo_og_title', (string) ($data['og_title'] ?? ''));
        MetaField::saveText($postId, '_scseo_og_description', (string) ($data['og_description'] ?? ''));

        $ogImage = (int) ($data['og_image'] ?? 0);
        MetaField::saveValue($postId, '_scseo_og_image', $ogImage > 0 ? (string) $ogImage : '');

        $schemaType = (string) ($data['schema_type'] ?? 'auto');
        MetaField::saveValue($postId, '_scseo_schema_type', \array_key_exists($schemaType, self::SCHEMA_TYPES) && $schemaType !== 'auto' ? $schemaType : '');
    }
}
