<?php

namespace SCSEO\MetaBoxes;

use SCSEO\Contracts\Hookable;
use SCSEO\Support\SeoMeta;

/**
 * The block-editor equivalent of SeoMetaBox — a native
 * PluginDocumentSettingPanel in the block editor's own sidebar,
 * for whichever post types actually use it (use_block_editor_for_post_type()).
 * Reads/writes the same _scseo_* postmeta as the classic box, made
 * possible by SeoMetaFields registering it as REST-visible.
 *
 * No build step, matching every other SC plugin: assets/js/editor-
 * sidebar.js is hand-written against the wp-* script handles core
 * already enqueues in the block editor, not bundled from JSX.
 */
final class SeoSidebarPanel implements Hookable
{
    public function register(): void
    {
        \add_action('enqueue_block_editor_assets', [$this, 'enqueueAssets']);
    }

    private function isEligible(string $postType): bool
    {
        return \in_array($postType, SeoMeta::eligiblePostTypes(), true) && \use_block_editor_for_post_type($postType);
    }

    public function enqueueAssets(): void
    {
        $screen = \get_current_screen();
        $postType = $screen->post_type ?? '';

        if (! $this->isEligible($postType)) {
            return;
        }

        \wp_enqueue_script(
            'scseo-editor-sidebar',
            SCSEO_URL.'assets/js/editor-sidebar.js',
            ['wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-compose'],
            SCSEO_VERSION,
            true
        );

        \wp_enqueue_style('scseo-admin', SCSEO_URL.'assets/css/admin.css', [], SCSEO_VERSION);

        \wp_localize_script('scseo-editor-sidebar', 'scseoSidebar', [
            'schemaTypes' => SeoMeta::SCHEMA_TYPES,
        ]);
    }
}
