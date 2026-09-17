<?php

namespace SCSEO\Support;

/**
 * Tiny postmeta read/write helper — an empty value always means
 * "delete the row" rather than storing an empty string, so postmeta
 * stays tidy. Copied from SC Maps' identical helper — see sc-seo.php's
 * own docblock for why it isn't shared instead.
 */
final class MetaField
{
    public static function saveText(int $postId, string $key, string $value): void
    {
        self::saveValue($postId, $key, \sanitize_text_field($value));
    }

    public static function saveValue(int $postId, string $key, string $value): void
    {
        if ($value === '') {
            \delete_post_meta($postId, $key);

            return;
        }

        \update_post_meta($postId, $key, $value);
    }
}
