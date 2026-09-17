<?php

namespace SCSEO\PostTypes;

use SCSEO\Contracts\Hookable;

/**
 * A single redirect: post_title is its source path, postmeta carries
 * target/type/hit stats (see Support\RedirectMeta). Not public, no
 * native UI — mirrors SC Room Bookings' BookingPostType for the same
 * reason: a redirect isn't "content" worth a post-edit screen, just a
 * handful of fields best managed as a table (see Admin\RedirectsPage).
 */
final class RedirectPostType implements Hookable
{
    public const POST_TYPE = 'scseo_redirect';

    public function register(): void
    {
        \add_action('init', [$this, 'registerPostType']);
    }

    public function registerPostType(): void
    {
        \register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'Redirects',
                'singular_name' => 'Redirect',
                'edit_item' => 'Edit Redirect',
                'view_item' => 'View Redirect',
                'search_items' => 'Search Redirects',
                'not_found' => 'No redirects found',
                'not_found_in_trash' => 'No redirects found in Trash',
                'all_items' => 'Redirects',
                'menu_name' => 'Redirects',
                'name_admin_bar' => 'Redirect',
            ],
            'public' => false,
            'show_ui' => false, // Admin\RedirectsPage is the only UI for these — no native post-new/edit screens.
            'show_in_rest' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
        ]);
    }
}
