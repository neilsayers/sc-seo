<?php

namespace SCSEO\MetaBoxes;

use SCSEO\Contracts\Hookable;
use SCSEO\Support\SeoMeta;

/**
 * The "SC SEO" box every public *classic-editor* post type gets —
 * title/description overrides with a live SERP-style preview,
 * canonical, robots, social overrides, and a schema-type choice.
 * Rendered via edit_form_after_title (add_meta_box() can only place
 * things below the editor) — same technique as SC Maps/SC Events
 * Manager/SC Room Bookings' own hand-written detail boxes.
 *
 * A post type edited in the block editor gets SeoSidebarPanel's native
 * Gutenberg panel instead, not this — both read/write the same
 * postmeta (see Support\SeoMeta and SeoMetaFields' REST registration
 * of it), so which one a given post type sees depends only on which
 * editor use_block_editor_for_post_type() says it uses. Showing both
 * at once would just be two boxes editing the same fields.
 *
 * Deliberately just these fields — no content/readability analysis,
 * no keyword density scoring. That's the whole point of this plugin
 * over Yoast/RankMath for a site that doesn't need it.
 */
final class SeoMetaBox implements Hookable
{
    private const NONCE_ACTION = 'scseo_save_meta_box';
    private const NONCE_NAME = 'scseo_meta_box_nonce';

    public function register(): void
    {
        \add_action('edit_form_after_title', [$this, 'renderMetaBox']);
        \add_action('save_post', [$this, 'saveMetaBox']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    private function isEligible(string $postType): bool
    {
        return \in_array($postType, SeoMeta::eligiblePostTypes(), true) && ! \use_block_editor_for_post_type($postType);
    }

    public function enqueueAssets(string $hook): void
    {
        if (! \in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $postType = \get_current_screen()->post_type ?? '';

        if (! $this->isEligible($postType)) {
            return;
        }

        \wp_enqueue_media();
        \wp_enqueue_style('scseo-admin', SCSEO_URL.'assets/css/admin.css', [], SCSEO_VERSION);
        \wp_enqueue_script('scseo-meta-box', SCSEO_URL.'assets/js/meta-box.js', ['jquery'], SCSEO_VERSION, true);
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        if (! $this->isEligible($post->post_type)) {
            return;
        }

        $meta = SeoMeta::read($post->ID);

        \wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        ?>
        <div class="postbox scseo-meta-box">
            <h2 class="hndle"><span>SC SEO</span></h2>
            <div class="inside">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="scseo_title">SEO title</label></th>
                        <td>
                            <input type="text" id="scseo_title" name="scseo[title]" value="<?php echo \esc_attr($meta['title']); ?>" class="large-text">
                            <p class="description">Leave blank to use the site's default title template. <span class="scseo-char-count" data-target="scseo_title" data-recommended="60"></span></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scseo_description">Meta description</label></th>
                        <td>
                            <textarea id="scseo_description" name="scseo[description]" rows="3" class="large-text"><?php echo \esc_textarea($meta['description']); ?></textarea>
                            <p class="description"><span class="scseo-char-count" data-target="scseo_description" data-recommended="155"></span></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">SERP preview</th>
                        <td><div id="scseo-serp-preview" class="scseo-serp-preview" data-fallback-title="<?php echo \esc_attr($post->post_title); ?>"></div></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scseo_canonical">Canonical URL</label></th>
                        <td>
                            <input type="url" id="scseo_canonical" name="scseo[canonical]" value="<?php echo \esc_attr($meta['canonical']); ?>" class="large-text" placeholder="<?php echo \esc_attr(\get_permalink($post)); ?>">
                            <p class="description">Only needed if this content is also reachable at another URL and this is the one you want indexed.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Robots</th>
                        <td>
                            <label><input type="checkbox" name="scseo[noindex]" value="1" <?php \checked($meta['noindex']); ?>> Discourage search engines from indexing this (noindex)</label><br>
                            <label><input type="checkbox" name="scseo[nofollow]" value="1" <?php \checked($meta['nofollow']); ?>> Don't follow links on this page (nofollow)</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scseo_og_title">Social title</label></th>
                        <td><input type="text" id="scseo_og_title" name="scseo[og_title]" value="<?php echo \esc_attr($meta['og_title']); ?>" class="large-text" placeholder="Falls back to the SEO title above"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scseo_og_description">Social description</label></th>
                        <td><textarea id="scseo_og_description" name="scseo[og_description]" rows="2" class="large-text" placeholder="Falls back to the meta description above"><?php echo \esc_textarea($meta['og_description']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row">Social image</th>
                        <td>
                            <input type="hidden" id="scseo_og_image" name="scseo[og_image]" value="<?php echo \esc_attr((string) $meta['og_image']); ?>">
                            <div id="scseo-og-image-preview" class="scseo-image-preview"><?php echo $meta['og_image'] ? \wp_get_attachment_image($meta['og_image'], 'medium') : ''; ?></div>
                            <p>
                                <button type="button" class="button scseo-image-select" data-input="scseo_og_image" data-preview="scseo-og-image-preview">Choose image</button>
                                <button type="button" class="button-link scseo-image-remove" data-input="scseo_og_image" data-preview="scseo-og-image-preview" <?php echo $meta['og_image'] ? '' : 'style="display:none"'; ?>>Remove</button>
                            </p>
                            <p class="description">Falls back to the featured image, then the sitewide default image (SC SEO → General).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scseo_schema_type">Structured data</label></th>
                        <td>
                            <select id="scseo_schema_type" name="scseo[schema_type]">
                                <?php foreach (SeoMeta::SCHEMA_TYPES as $value => $label) : ?>
                                    <option value="<?php echo \esc_attr($value); ?>" <?php \selected($value === $meta['schema_type'] || ($value === 'auto' && $meta['schema_type'] === '')); ?>><?php echo \esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    public function saveMetaBox(int $postId): void
    {
        if (
            ! isset($_POST[self::NONCE_NAME])
            || ! \wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
        ) {
            return;
        }

        if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $post = \get_post($postId);

        if (! $post || ! $this->isEligible($post->post_type) || ! \current_user_can('edit_post', $postId)) {
            return;
        }

        $data = \wp_unslash($_POST['scseo'] ?? []);

        SeoMeta::save($postId, $data);
    }
}
