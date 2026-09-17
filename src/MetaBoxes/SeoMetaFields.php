<?php

namespace SCSEO\MetaBoxes;

use SCSEO\Contracts\Hookable;
use SCSEO\Support\SeoMeta;

/**
 * Registers every _scseo_* field as REST-visible postmeta. Needed for
 * two separate reasons that happen to share one registration: it's
 * what lets SeoSidebarPanel's Gutenberg panel read/write these fields
 * at all (the block editor only ever talks to postmeta through the
 * REST API, never through admin-post.php), and it's what makes the
 * data show up in the REST API generally for anyone consuming it
 * that way. The classic metabox (SeoMetaBox) doesn't need this — it
 * reads/writes postmeta directly — but registering it regardless of
 * which editor a post type uses costs nothing and keeps this the one
 * place that defines the field shape.
 */
final class SeoMetaFields implements Hookable
{
    public function register(): void
    {
        \add_action('init', [$this, 'registerFields']);
    }

    public function registerFields(): void
    {
        foreach (SeoMeta::eligiblePostTypes() as $postType) {
            $this->registerField($postType, '_scseo_title', 'string', 'sanitize_text_field');
            $this->registerField($postType, '_scseo_description', 'string', 'sanitize_text_field');
            $this->registerField($postType, '_scseo_canonical', 'string', 'esc_url_raw');
            $this->registerField($postType, '_scseo_noindex', 'boolean');
            $this->registerField($postType, '_scseo_nofollow', 'boolean');
            $this->registerField($postType, '_scseo_og_title', 'string', 'sanitize_text_field');
            $this->registerField($postType, '_scseo_og_description', 'string', 'sanitize_text_field');
            $this->registerField($postType, '_scseo_og_image', 'integer', 'absint');
            $this->registerField($postType, '_scseo_schema_type', 'string', [$this, 'sanitizeSchemaType']);
        }
    }

    /**
     * @param string|callable|null $sanitizeCallback
     */
    private function registerField(string $postType, string $key, string $type, $sanitizeCallback = null): void
    {
        \register_post_meta($postType, $key, [
            'type' => $type,
            'single' => true,
            'default' => $type === 'boolean' ? false : ($type === 'integer' ? 0 : ''),
            'show_in_rest' => true,
            'sanitize_callback' => $sanitizeCallback,
            'auth_callback' => static fn (bool $allowed, string $metaKey, int $postId): bool => \current_user_can('edit_post', $postId),
        ]);
    }

    public function sanitizeSchemaType(string $value): string
    {
        return \array_key_exists($value, SeoMeta::SCHEMA_TYPES) && $value !== 'auto' ? $value : '';
    }
}
