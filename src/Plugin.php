<?php

namespace SCSEO;

use SCSEO\Admin\DocumentationPage;
use SCSEO\Admin\RedirectsPage;
use SCSEO\Admin\SettingsPage;
use SCSEO\Frontend\AnalyticsOutput;
use SCSEO\Frontend\AttachmentRedirect;
use SCSEO\Frontend\FeedsDisabled;
use SCSEO\Frontend\HeadOutput;
use SCSEO\Frontend\RedirectHandler;
use SCSEO\Frontend\SchemaOutput;
use SCSEO\Frontend\SitemapFilters;
use SCSEO\MetaBoxes\SeoMetaBox;
use SCSEO\MetaBoxes\SeoMetaFields;
use SCSEO\MetaBoxes\SeoSidebarPanel;
use SCSEO\PostTypes\RedirectPostType;
use SCSEO\Settings\Settings;

/**
 * Composes the plugin's features and wires them into WordPress.
 *
 * To grow the plugin (an Organization node contributed by another SC
 * plugin, a bulk noindex tool, ...) write a class implementing
 * Contracts\Hookable and add it to the list in boot().
 */
final class Plugin
{
    private static ?self $instance = null;

    private Settings $settings;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->settings = new Settings();
    }

    public function boot(): void
    {
        $features = [
            new SettingsPage($this->settings),
            new RedirectsPage(),
            new DocumentationPage(),
            new RedirectPostType(),
            new SeoMetaFields(),
            new SeoMetaBox(),
            new SeoSidebarPanel(),
            new HeadOutput($this->settings),
            new SchemaOutput($this->settings),
            new AnalyticsOutput($this->settings),
            new SitemapFilters($this->settings),
            new RedirectHandler(),
            new AttachmentRedirect($this->settings),
            new FeedsDisabled($this->settings),
        ];

        foreach ($features as $feature) {
            $feature->register();
        }
    }

    public function settings(): Settings
    {
        return $this->settings;
    }
}
