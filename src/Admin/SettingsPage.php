<?php

namespace SCSEO\Admin;

use SCSEO\Contracts\Hookable;
use SCSEO\Settings\Settings;

/**
 * The plugin's top-level "SC SEO" menu — General / Social / Business &
 * Schema tabs over the one scseo_settings option row. Same one-page,
 * one-form-per-tab shape as SC Maps/SC Events Manager/SC Room
 * Bookings' own settings screens, adapted for plain fields instead of
 * a repeating list of user-defined types.
 */
final class SettingsPage implements Hookable
{
    public const PAGE_SLUG = 'scseo-settings';
    private const SAVE_ACTION = 'scseo_save_settings';

    private const TABS = [
        'general' => 'General',
        'social' => 'Social',
        'schema' => 'Business & Schema',
    ];

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        \add_action('admin_post_'.self::SAVE_ACTION, [$this, 'handleSave']);
    }

    public function registerMenu(): void
    {
        \add_menu_page(
            'SC SEO',
            'SC SEO',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage'],
            'dashicons-search',
            93
        );

        \add_submenu_page(
            self::PAGE_SLUG,
            'SC SEO',
            'Settings',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if (($_GET['page'] ?? '') !== self::PAGE_SLUG) {
            return;
        }

        \wp_enqueue_media();
        \wp_enqueue_style('scseo-admin', SCSEO_URL.'assets/css/admin.css', [], SCSEO_VERSION);
        \wp_enqueue_script('scseo-meta-box', SCSEO_URL.'assets/js/meta-box.js', ['jquery'], SCSEO_VERSION, true);
    }

    private function currentTab(): string
    {
        $tab = \sanitize_key((string) ($_GET['tab'] ?? 'general'));

        return \array_key_exists($tab, self::TABS) ? $tab : 'general';
    }

    public function handleSave(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die('You do not have permission to do this.');
        }

        \check_admin_referer(self::SAVE_ACTION);

        $tab = \sanitize_key((string) ($_POST['tab'] ?? 'general'));
        $data = \wp_unslash($_POST['scseo_settings'] ?? []);

        $this->settings->update(match ($tab) {
            'social' => $this->sanitizeSocial($data),
            'schema' => $this->sanitizeSchema($data),
            default => $this->sanitizeGeneral($data),
        });

        \add_settings_error('scseo_settings', 'settings-updated', 'Settings saved.', 'success');
        \set_transient('settings_errors', \get_settings_errors(), 30);

        \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&tab='.$tab.'&settings-updated=true'));
        exit;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitizeGeneral(array $data): array
    {
        $templates = [];

        foreach ((array) ($data['title_templates'] ?? []) as $postType => $template) {
            $postType = \sanitize_key((string) $postType);
            $template = \sanitize_text_field((string) $template);

            if ($postType !== '' && $template !== '') {
                $templates[$postType] = $template;
            }
        }

        $gaId = \strtoupper(\trim((string) ($data['ga_measurement_id'] ?? '')));

        return [
            'title_separator' => \sanitize_text_field((string) ($data['title_separator'] ?? '–')) ?: '–',
            'home_title' => \sanitize_text_field((string) ($data['home_title'] ?? '')),
            'home_description' => \sanitize_textarea_field((string) ($data['home_description'] ?? '')),
            'default_robots_noindex' => ! empty($data['default_robots_noindex']),
            'disable_feeds' => ! empty($data['disable_feeds']),
            'noindex_thin_archives' => ! empty($data['noindex_thin_archives']),
            'redirect_attachment_pages' => ! empty($data['redirect_attachment_pages']),
            'default_social_image' => (int) ($data['default_social_image'] ?? 0),
            'title_templates' => $templates,
            'ga_measurement_id' => \preg_match('/^G-[A-Z0-9]+$/', $gaId) ? $gaId : '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitizeSocial(array $data): array
    {
        return [
            'facebook_url' => \esc_url_raw((string) ($data['facebook_url'] ?? '')),
            'twitter_handle' => \sanitize_text_field(\ltrim((string) ($data['twitter_handle'] ?? ''), '@')),
            'instagram_url' => \esc_url_raw((string) ($data['instagram_url'] ?? '')),
            'linkedin_url' => \esc_url_raw((string) ($data['linkedin_url'] ?? '')),
            'youtube_url' => \esc_url_raw((string) ($data['youtube_url'] ?? '')),
            'facebook_app_id' => \sanitize_text_field((string) ($data['facebook_app_id'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitizeSchema(array $data): array
    {
        $type = (string) ($data['business_type'] ?? 'Organization');

        return [
            'business_type' => \array_key_exists($type, Settings::BUSINESS_TYPES) ? $type : 'Organization',
            'business_name' => \sanitize_text_field((string) ($data['business_name'] ?? '')),
            'business_logo' => (int) ($data['business_logo'] ?? 0),
            'business_phone' => \sanitize_text_field((string) ($data['business_phone'] ?? '')),
            'business_email' => \sanitize_email((string) ($data['business_email'] ?? '')),
            'business_address_street' => \sanitize_text_field((string) ($data['business_address_street'] ?? '')),
            'business_address_locality' => \sanitize_text_field((string) ($data['business_address_locality'] ?? '')),
            'business_address_region' => \sanitize_text_field((string) ($data['business_address_region'] ?? '')),
            'business_address_postcode' => \sanitize_text_field((string) ($data['business_address_postcode'] ?? '')),
            'business_address_country' => \sanitize_text_field((string) ($data['business_address_country'] ?? '')),
            'business_hours' => \sanitize_textarea_field((string) ($data['business_hours'] ?? '')),
        ];
    }

    public function renderPage(): void
    {
        if (! \current_user_can('manage_options')) {
            return;
        }

        $tab = $this->currentTab();
        ?>
        <div class="wrap">
            <h1>SC SEO</h1>

            <?php \settings_errors('scseo_settings'); ?>

            <h2 class="nav-tab-wrapper">
                <?php foreach (self::TABS as $slug => $label) : ?>
                    <a href="<?php echo \esc_url(\admin_url('admin.php?page='.self::PAGE_SLUG.'&tab='.$slug)); ?>" class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>"><?php echo \esc_html($label); ?></a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>">
                <?php \wp_nonce_field(self::SAVE_ACTION); ?>
                <input type="hidden" name="action" value="<?php echo \esc_attr(self::SAVE_ACTION); ?>">
                <input type="hidden" name="tab" value="<?php echo \esc_attr($tab); ?>">

                <?php match ($tab) {
                    'social' => $this->renderSocialTab(),
                    'schema' => $this->renderSchemaTab(),
                    default => $this->renderGeneralTab(),
                }; ?>

                <?php \submit_button('Save settings'); ?>
            </form>
        </div>
        <?php
    }

    private function renderGeneralTab(): void
    {
        $s = $this->settings;
        $postTypes = \get_post_types(['public' => true, 'show_ui' => true], 'objects');
        $templates = $s->get('title_templates', []);
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="scseo_title_separator">Title separator</label></th>
                <td><input type="text" id="scseo_title_separator" name="scseo_settings[title_separator]" value="<?php echo \esc_attr((string) $s->get('title_separator', '–')); ?>" class="small-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="scseo_home_title">Homepage title</label></th>
                <td><input type="text" id="scseo_home_title" name="scseo_settings[home_title]" value="<?php echo \esc_attr((string) $s->get('home_title')); ?>" class="large-text" placeholder="%sitename%"></td>
            </tr>
            <tr>
                <th scope="row"><label for="scseo_home_description">Homepage description</label></th>
                <td><textarea id="scseo_home_description" name="scseo_settings[home_description]" rows="3" class="large-text"><?php echo \esc_textarea((string) $s->get('home_description')); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><label for="scseo_ga_measurement_id">Google Analytics</label></th>
                <td>
                    <input type="text" id="scseo_ga_measurement_id" name="scseo_settings[ga_measurement_id]" value="<?php echo \esc_attr((string) $s->get('ga_measurement_id')); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
                    <p class="description">GA4 Measurement ID, from Analytics → Admin → Data Streams. Leave blank and no tracking script is printed at all. Skipped automatically when "Discourage search engines" below is on, so staging traffic never reaches your real property.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Default robots</th>
                <td>
                    <label><input type="checkbox" name="scseo_settings[default_robots_noindex]" value="1" <?php \checked($s->get('default_robots_noindex')); ?>> Discourage search engines from indexing this entire site (noindex everywhere — for staging sites)</label>
                </td>
            </tr>
            <tr>
                <th scope="row">Feeds</th>
                <td>
                    <label><input type="checkbox" name="scseo_settings[disable_feeds]" value="1" <?php \checked($s->get('disable_feeds')); ?>> Turn off RSS/Atom feeds</label>
                    <p class="description">Closes every feed WordPress generates by default — the main site feed plus a separate one per tag, per author, per comment thread and for search — and stops advertising them in the page &lt;head&gt;. Search engines find and crawl these even with nothing linking to them, and an unused feed just shows up in Search Console as low-value content nobody asked for. Leave this off if anyone actually subscribes to this site's RSS, or if it feeds a newsletter tool or reader integration.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Archive &amp; attachment cleanup</th>
                <td>
                    <p>
                        <label><input type="checkbox" name="scseo_settings[noindex_thin_archives]" value="1" <?php \checked($s->get('noindex_thin_archives')); ?>> Noindex thin archive pages</label>
                    </p>
                    <p class="description">Marks the author archive, date archives, and any tag/category archive listing only one post as noindex. Each of those is a near-duplicate of content already indexed elsewhere — one post repeated under a different URL — and is exactly what Search Console reports as "Crawled" or "Discovered — currently not indexed". This tells search engines not to bother rather than leaving them to work it out per page.</p>
                    <p style="margin-top:1em">
                        <label><input type="checkbox" name="scseo_settings[redirect_attachment_pages]" value="1" <?php \checked($s->get('redirect_attachment_pages')); ?>> Redirect attachment pages</label>
                    </p>
                    <p class="description">WordPress gives every uploaded image its own standalone page containing nothing but that image and its title. Nothing on this site links to them on purpose, but search engines find and crawl them anyway — a common source of thin-content warnings. Turning this on sends a visit to one straight to the post or page it belongs to (or the homepage, if it isn't attached to anything) and drops attachments from the XML sitemap.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Default social image</th>
                <td>
                    <input type="hidden" id="scseo_default_social_image" name="scseo_settings[default_social_image]" value="<?php echo \esc_attr((string) $s->get('default_social_image', 0)); ?>">
                    <div id="scseo-default-social-image-preview" class="scseo-image-preview"><?php $id = (int) $s->get('default_social_image', 0); echo $id ? \wp_get_attachment_image($id, 'medium') : ''; ?></div>
                    <p>
                        <button type="button" class="button scseo-image-select" data-input="scseo_default_social_image" data-preview="scseo-default-social-image-preview">Choose image</button>
                        <button type="button" class="button-link scseo-image-remove" data-input="scseo_default_social_image" data-preview="scseo-default-social-image-preview" <?php echo $id ? '' : 'style="display:none"'; ?>>Remove</button>
                    </p>
                    <p class="description">Used when a page has no featured image and no per-post social image of its own.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Title templates</th>
                <td>
                    <p class="description" style="margin-top:0">Tokens: <code>%title%</code> <code>%sitename%</code> <code>%sep%</code> <code>%excerpt%</code></p>
                    <?php foreach ($postTypes as $postType) : ?>
                        <p>
                            <label style="display:inline-block;width:140px;"><?php echo \esc_html($postType->labels->singular_name); ?></label>
                            <input type="text" name="scseo_settings[title_templates][<?php echo \esc_attr($postType->name); ?>]" value="<?php echo \esc_attr($templates[$postType->name] ?? '%title% %sep% %sitename%'); ?>" class="regular-text">
                        </p>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    private function renderSocialTab(): void
    {
        $s = $this->settings;
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="scseo_facebook_url">Facebook Page URL</label></th>
                <td><input type="url" id="scseo_facebook_url" name="scseo_settings[facebook_url]" value="<?php echo \esc_attr((string) $s->get('facebook_url')); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="scseo_twitter_handle">X / Twitter handle</label></th>
                <td>@<input type="text" id="scseo_twitter_handle" name="scseo_settings[twitter_handle]" value="<?php echo \esc_attr((string) $s->get('twitter_handle')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="scseo_instagram_url">Instagram URL</label></th>
                <td><input type="url" id="scseo_instagram_url" name="scseo_settings[instagram_url]" value="<?php echo \esc_attr((string) $s->get('instagram_url')); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="scseo_linkedin_url">LinkedIn URL</label></th>
                <td><input type="url" id="scseo_linkedin_url" name="scseo_settings[linkedin_url]" value="<?php echo \esc_attr((string) $s->get('linkedin_url')); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="scseo_youtube_url">YouTube URL</label></th>
                <td><input type="url" id="scseo_youtube_url" name="scseo_settings[youtube_url]" value="<?php echo \esc_attr((string) $s->get('youtube_url')); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="scseo_facebook_app_id">Facebook App ID</label></th>
                <td>
                    <input type="text" id="scseo_facebook_app_id" name="scseo_settings[facebook_app_id]" value="<?php echo \esc_attr((string) $s->get('facebook_app_id')); ?>" class="regular-text">
                    <p class="description">Optional — only needed for Facebook Insights on shared links.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function renderSchemaTab(): void
    {
        $s = $this->settings;
        $isLocal = $s->isLocalBusiness();
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="scseo_business_type">Business type</label></th>
                <td>
                    <select id="scseo_business_type" name="scseo_settings[business_type]">
                        <?php foreach (Settings::BUSINESS_TYPES as $value => $label) : ?>
                            <option value="<?php echo \esc_attr($value); ?>" <?php \selected($value === $s->get('business_type')); ?>><?php echo \esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Feeds the Organization/LocalBusiness structured data on every page — this is what helps Google's Knowledge Panel and AI answer engines identify who the site belongs to.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="scseo_business_name">Business name</label></th>
                <td><input type="text" id="scseo_business_name" name="scseo_settings[business_name]" value="<?php echo \esc_attr((string) $s->get('business_name')); ?>" class="large-text" placeholder="<?php echo \esc_attr(\get_bloginfo('name')); ?>"></td>
            </tr>
            <tr>
                <th scope="row">Logo</th>
                <td>
                    <input type="hidden" id="scseo_business_logo" name="scseo_settings[business_logo]" value="<?php echo \esc_attr((string) $s->get('business_logo', 0)); ?>">
                    <div id="scseo-business-logo-preview" class="scseo-image-preview"><?php $id = (int) $s->get('business_logo', 0); echo $id ? \wp_get_attachment_image($id, 'medium') : ''; ?></div>
                    <p>
                        <button type="button" class="button scseo-image-select" data-input="scseo_business_logo" data-preview="scseo-business-logo-preview">Choose image</button>
                        <button type="button" class="button-link scseo-image-remove" data-input="scseo_business_logo" data-preview="scseo-business-logo-preview" <?php echo $id ? '' : 'style="display:none"'; ?>>Remove</button>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="scseo_business_phone">Phone</label></th>
                <td><input type="text" id="scseo_business_phone" name="scseo_settings[business_phone]" value="<?php echo \esc_attr((string) $s->get('business_phone')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="scseo_business_email">Email</label></th>
                <td><input type="email" id="scseo_business_email" name="scseo_settings[business_email]" value="<?php echo \esc_attr((string) $s->get('business_email')); ?>" class="regular-text"></td>
            </tr>
            <tr class="scseo-local-only" <?php echo $isLocal ? '' : 'style="display:none"'; ?>>
                <th scope="row">Address</th>
                <td>
                    <p><input type="text" name="scseo_settings[business_address_street]" value="<?php echo \esc_attr((string) $s->get('business_address_street')); ?>" class="large-text" placeholder="Street address"></p>
                    <p>
                        <input type="text" name="scseo_settings[business_address_locality]" value="<?php echo \esc_attr((string) $s->get('business_address_locality')); ?>" placeholder="Town / city">
                        <input type="text" name="scseo_settings[business_address_region]" value="<?php echo \esc_attr((string) $s->get('business_address_region')); ?>" placeholder="County">
                        <input type="text" name="scseo_settings[business_address_postcode]" value="<?php echo \esc_attr((string) $s->get('business_address_postcode')); ?>" placeholder="Postcode" class="small-text">
                    </p>
                    <p><input type="text" name="scseo_settings[business_address_country]" value="<?php echo \esc_attr((string) $s->get('business_address_country')); ?>" placeholder="Country"></p>
                </td>
            </tr>
            <tr class="scseo-local-only" <?php echo $isLocal ? '' : 'style="display:none"'; ?>>
                <th scope="row"><label for="scseo_business_hours">Opening hours</label></th>
                <td>
                    <textarea id="scseo_business_hours" name="scseo_settings[business_hours]" rows="4" class="large-text" placeholder="Mo-Fr 09:00-17:00&#10;Sa 10:00-14:00"><?php echo \esc_textarea((string) $s->get('business_hours')); ?></textarea>
                    <p class="description">One per line, schema.org format (e.g. <code>Mo-Fr 09:00-17:00</code>).</p>
                </td>
            </tr>
        </table>
        <script>
        (function () {
            var typeSelect = document.getElementById('scseo_business_type');
            var localTypes = <?php echo \wp_json_encode(['LocalBusiness', 'ProfessionalService', 'Store', 'Restaurant']); ?>;
            var rows = document.querySelectorAll('.scseo-local-only');

            if (! typeSelect) {
                return;
            }

            typeSelect.addEventListener('change', function () {
                var show = localTypes.indexOf(typeSelect.value) !== -1;
                rows.forEach(function (row) { row.style.display = show ? '' : 'none'; });
            });
        })();
        </script>
        <?php
    }
}
