<?php

namespace SCSEO\Admin;

use SCSEO\Contracts\Hookable;
use SCSEO\PostTypes\RedirectPostType;
use SCSEO\Support\RedirectCache;
use SCSEO\Support\RedirectMeta;

/**
 * "Redirects" — every scseo_redirect post as one table, plus an
 * add-one form and a CSV bulk-import tool for migrating a retired
 * site's URLs in one go. A plain HTML table rather than WP_List_Table
 * — same lighter-weight approach SC Maps/SC Events Manager/SC Room
 * Bookings use for their own admin list screens.
 */
final class RedirectsPage implements Hookable
{
    private const PAGE_SLUG = 'scseo-redirects';
    private const ADD_ACTION = 'scseo_add_redirect';
    private const EDIT_ACTION = 'scseo_edit_redirect';
    private const DELETE_ACTION = 'scseo_delete_redirect';
    private const TOGGLE_ACTION = 'scseo_toggle_redirect';
    private const IMPORT_ACTION = 'scseo_import_redirects';

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        \add_action('admin_post_'.self::ADD_ACTION, [$this, 'handleAdd']);
        \add_action('admin_post_'.self::EDIT_ACTION, [$this, 'handleEdit']);
        \add_action('admin_post_'.self::DELETE_ACTION, [$this, 'handleDelete']);
        \add_action('admin_post_'.self::TOGGLE_ACTION, [$this, 'handleToggle']);
        \add_action('admin_post_'.self::IMPORT_ACTION, [$this, 'handleImport']);
    }

    public function registerMenu(): void
    {
        \add_submenu_page(
            SettingsPage::PAGE_SLUG,
            'Redirects',
            'Redirects',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function enqueueAssets(): void
    {
        if (($_GET['page'] ?? '') !== self::PAGE_SLUG) {
            return;
        }

        \wp_enqueue_style('scseo-admin', SCSEO_URL.'assets/css/admin.css', [], SCSEO_VERSION);
        \wp_enqueue_script('scseo-redirects', SCSEO_URL.'assets/js/redirects.js', [], SCSEO_VERSION, true);
    }

    /**
     * A source is only ever authored/matched as a normalized path —
     * this is the single place user input becomes that, for both the
     * one-at-a-time form and the CSV importer.
     */
    private function normalizeSource(string $source): string
    {
        return RedirectCache::normalize(\sanitize_text_field($source));
    }

    private function findBySource(string $normalizedSource, int $excludePostId = 0): ?int
    {
        $posts = \get_posts([
            'post_type' => RedirectPostType::POST_TYPE,
            'post_status' => 'any',
            'title' => $normalizedSource,
            'posts_per_page' => 1,
            'exclude' => $excludePostId ? [$excludePostId] : [],
            'no_found_rows' => true,
        ]);

        return $posts[0]->ID ?? null;
    }

    public function handleAdd(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die('You do not have permission to do this.');
        }

        \check_admin_referer(self::ADD_ACTION);

        $source = $this->normalizeSource((string) ($_POST['source'] ?? ''));
        $target = \esc_url_raw((string) \wp_unslash($_POST['target'] ?? ''));
        $type = (string) ($_POST['type'] ?? '301');

        if ($source === '' || $source === '/' || $target === '') {
            \add_settings_error('scseo_redirects', 'invalid', 'Please enter both a source path and a destination.', 'error');
        } elseif ($this->findBySource($source) !== null) {
            \add_settings_error('scseo_redirects', 'duplicate', \sprintf('A redirect for "%s" already exists.', $source), 'error');
        } else {
            $postId = \wp_insert_post([
                'post_type' => RedirectPostType::POST_TYPE,
                'post_title' => $source,
                'post_status' => 'publish',
            ], true);

            if (! \is_wp_error($postId)) {
                RedirectMeta::save($postId, $target, $type);
                RedirectCache::rebuild();
                \add_settings_error('scseo_redirects', 'added', 'Redirect added.', 'success');
            }
        }

        $this->redirectBack();
    }

    public function handleEdit(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die('You do not have permission to do this.');
        }

        \check_admin_referer(self::EDIT_ACTION);

        $postId = (int) ($_POST['post_id'] ?? 0);
        $source = $this->normalizeSource((string) ($_POST['source'] ?? ''));
        $target = \esc_url_raw((string) \wp_unslash($_POST['target'] ?? ''));
        $type = (string) ($_POST['type'] ?? '301');

        if (! \get_post($postId) || $source === '' || $source === '/' || $target === '') {
            \add_settings_error('scseo_redirects', 'invalid', 'Please enter both a source path and a destination.', 'error');
        } elseif ($this->findBySource($source, $postId) !== null) {
            \add_settings_error('scseo_redirects', 'duplicate', \sprintf('A redirect for "%s" already exists.', $source), 'error');
        } else {
            \wp_update_post(['ID' => $postId, 'post_title' => $source]);
            RedirectMeta::save($postId, $target, $type);
            RedirectCache::rebuild();
            \add_settings_error('scseo_redirects', 'updated', 'Redirect updated.', 'success');
        }

        $this->redirectBack();
    }

    public function handleDelete(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die('You do not have permission to do this.');
        }

        \check_admin_referer(self::DELETE_ACTION);

        $postId = (int) ($_POST['post_id'] ?? 0);
        $post = \get_post($postId);

        if ($post && $post->post_type === RedirectPostType::POST_TYPE) {
            \wp_delete_post($postId, true);
            RedirectCache::rebuild();
            \add_settings_error('scseo_redirects', 'deleted', 'Redirect deleted.', 'success');
        }

        $this->redirectBack();
    }

    public function handleToggle(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die('You do not have permission to do this.');
        }

        \check_admin_referer(self::TOGGLE_ACTION);

        $postId = (int) ($_POST['post_id'] ?? 0);
        $post = \get_post($postId);

        if ($post && $post->post_type === RedirectPostType::POST_TYPE) {
            \wp_update_post(['ID' => $postId, 'post_status' => $post->post_status === 'publish' ? 'draft' : 'publish']);
            RedirectCache::rebuild();
        }

        $this->redirectBack();
    }

    /**
     * Rows: source,destination,type (type optional, defaults to 301).
     * A header row is tolerated — skipped when its first cell doesn't
     * look like a path (doesn't start with "/"). Existing sources and
     * in-file duplicates are skipped, not overwritten, and reported
     * back in one summary notice rather than silently merged.
     */
    public function handleImport(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die('You do not have permission to do this.');
        }

        \check_admin_referer(self::IMPORT_ACTION);

        if (empty($_FILES['scseo_csv']['tmp_name']) || ! \is_uploaded_file($_FILES['scseo_csv']['tmp_name'])) {
            \add_settings_error('scseo_redirects', 'no-file', 'Please choose a CSV file to import.', 'error');
            $this->redirectBack();
        }

        $handle = \fopen($_FILES['scseo_csv']['tmp_name'], 'r');

        if (! $handle) {
            \add_settings_error('scseo_redirects', 'unreadable', 'Could not read that file.', 'error');
            $this->redirectBack();
        }

        $created = 0;
        $skipped = 0;
        $seen = [];
        $rowNumber = 0;

        while (($row = \fgetcsv($handle)) !== false) {
            $rowNumber++;
            $source = $this->normalizeSource((string) ($row[0] ?? ''));

            if ($rowNumber === 1 && ! \str_starts_with((string) ($row[0] ?? ''), '/')) {
                continue; // Header row.
            }

            $target = \esc_url_raw(\trim((string) ($row[1] ?? '')));
            $type = \trim((string) ($row[2] ?? '')) ?: '301';

            if ($source === '' || $source === '/' || $target === '' || isset($seen[$source]) || $this->findBySource($source) !== null) {
                $skipped++;

                continue;
            }

            $seen[$source] = true;

            $postId = \wp_insert_post([
                'post_type' => RedirectPostType::POST_TYPE,
                'post_title' => $source,
                'post_status' => 'publish',
            ], true);

            if (\is_wp_error($postId)) {
                $skipped++;

                continue;
            }

            RedirectMeta::save($postId, $target, $type);
            $created++;
        }

        \fclose($handle);
        RedirectCache::rebuild();

        \add_settings_error(
            'scseo_redirects',
            'imported',
            \sprintf('Import finished: %d redirect(s) added, %d row(s) skipped (duplicate or invalid).', $created, $skipped),
            $created > 0 ? 'success' : 'warning'
        );

        $this->redirectBack();
    }

    private function redirectBack(): void
    {
        \set_transient('settings_errors', \get_settings_errors(), 30);
        \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&settings-updated=true'));
        exit;
    }

    public function renderPage(): void
    {
        if (! \current_user_can('manage_options')) {
            return;
        }

        $editId = (int) ($_GET['edit'] ?? 0);
        $editPost = $editId ? \get_post($editId) : null;
        $editMeta = $editPost ? RedirectMeta::read($editId) : null;

        $redirects = \get_posts([
            'post_type' => RedirectPostType::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);
        ?>
        <div class="wrap">
            <h1>SC SEO — Redirects</h1>

            <?php \settings_errors('scseo_redirects'); ?>

            <h2><?php echo $editPost ? 'Edit redirect' : 'Add a redirect'; ?></h2>
            <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>" class="scseo-redirect-form">
                <?php \wp_nonce_field($editPost ? self::EDIT_ACTION : self::ADD_ACTION); ?>
                <input type="hidden" name="action" value="<?php echo \esc_attr($editPost ? self::EDIT_ACTION : self::ADD_ACTION); ?>">
                <?php if ($editPost) : ?>
                    <input type="hidden" name="post_id" value="<?php echo \esc_attr((string) $editPost->ID); ?>">
                <?php endif; ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="scseo_source">Source path</label></th>
                        <td><input type="text" id="scseo_source" name="source" value="<?php echo \esc_attr($editPost ? $editPost->post_title : ''); ?>" class="regular-text" placeholder="/old-page" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scseo_target">Destination</label></th>
                        <td><input type="text" id="scseo_target" name="target" value="<?php echo \esc_attr($editMeta['target'] ?? ''); ?>" class="regular-text" placeholder="/new-page or https://example.com/page" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scseo_type">Type</label></th>
                        <td>
                            <select id="scseo_type" name="type">
                                <?php foreach (RedirectMeta::TYPES as $value => $label) : ?>
                                    <option value="<?php echo \esc_attr($value); ?>" <?php \selected($value === ($editMeta['type'] ?? '301')); ?>><?php echo \esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php \submit_button($editPost ? 'Save changes' : 'Add redirect'); ?>
                <?php if ($editPost) : ?>
                    <a href="<?php echo \esc_url(\admin_url('admin.php?page='.self::PAGE_SLUG)); ?>" class="button-link">Cancel</a>
                <?php endif; ?>
            </form>

            <h2>Import from CSV</h2>
            <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php \wp_nonce_field(self::IMPORT_ACTION); ?>
                <input type="hidden" name="action" value="<?php echo \esc_attr(self::IMPORT_ACTION); ?>">
                <p>
                    <input type="file" name="scseo_csv" accept=".csv,text/csv" required>
                    <?php \submit_button('Import redirects', 'secondary', 'submit', false); ?>
                </p>
                <p class="description">Columns: <code>source,destination,type</code> — type is optional, defaults to 301. A header row is fine. Existing sources are skipped, never overwritten.</p>
            </form>

            <h2>Redirects (<?php echo \count($redirects); ?>)</h2>
            <?php if ($redirects === []) : ?>
                <p>No redirects yet.</p>
            <?php else : ?>
                <table class="widefat striped" style="max-width: 1200px;">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Destination</th>
                            <th>Type</th>
                            <th>Hits</th>
                            <th>Last hit</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($redirects as $redirect) :
                            $meta = RedirectMeta::read($redirect->ID);
                            ?>
                            <tr>
                                <td><code><?php echo \esc_html($redirect->post_title); ?></code></td>
                                <td><?php echo \esc_html($meta['target']); ?></td>
                                <td><?php echo \esc_html($meta['type']); ?></td>
                                <td><?php echo \esc_html((string) $meta['hits']); ?></td>
                                <td><?php echo \esc_html($meta['last_hit'] ?: '—'); ?></td>
                                <td><?php echo $redirect->post_status === 'publish' ? 'Active' : 'Paused'; ?></td>
                                <td>
                                    <a href="<?php echo \esc_url(\admin_url('admin.php?page='.self::PAGE_SLUG.'&edit='.$redirect->ID)); ?>">Edit</a>

                                    <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>" style="display:inline">
                                        <?php \wp_nonce_field(self::TOGGLE_ACTION); ?>
                                        <input type="hidden" name="action" value="<?php echo \esc_attr(self::TOGGLE_ACTION); ?>">
                                        <input type="hidden" name="post_id" value="<?php echo \esc_attr((string) $redirect->ID); ?>">
                                        <button type="submit" class="button-link"><?php echo $redirect->post_status === 'publish' ? 'Pause' : 'Activate'; ?></button>
                                    </form>

                                    <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>" style="display:inline" class="scseo-delete-redirect">
                                        <?php \wp_nonce_field(self::DELETE_ACTION); ?>
                                        <input type="hidden" name="action" value="<?php echo \esc_attr(self::DELETE_ACTION); ?>">
                                        <input type="hidden" name="post_id" value="<?php echo \esc_attr((string) $redirect->ID); ?>">
                                        <button type="submit" class="button-link">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
