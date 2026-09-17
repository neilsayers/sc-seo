<?php

namespace SCSEO\Setup;

use SCSEO\Support\RedirectCache;

final class Activator
{
    public const REDIRECT_TRANSIENT = 'scseo_activation_redirect';

    public static function activate(): void
    {
        RedirectCache::rebuild();

        \set_transient(self::REDIRECT_TRANSIENT, true, 30);
    }

    public static function deactivate(): void
    {
        \flush_rewrite_rules();
    }
}
