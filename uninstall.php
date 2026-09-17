<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('scseo_settings');
delete_option('scseo_redirect_cache');

$redirectIds = get_posts([
    'post_type' => 'scseo_redirect',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids',
]);

foreach ($redirectIds as $postId) {
    wp_delete_post($postId, true);
}
