<?php

namespace SCSEO\Frontend;

use SCSEO\Contracts\Hookable;
use SCSEO\Settings\Settings;

/**
 * WordPress core has served an XML sitemap at /wp-sitemap.xml since
 * 5.5 — no need for this plugin to build another one. The only gap is
 * that core doesn't know about this plugin's noindex flag, so a post
 * marked noindex here would still show up there. This closes that gap
 * by excluding it from the query core builds the sitemap from, rather
 * than post-filtering entries after the fact.
 *
 * Also keeps the sitemap in step with the same two opt-in settings
 * HeadOutput enforces on the pages themselves — there is no point
 * submitting a URL to Google that the page it points to has just told
 * Google to ignore.
 */
final class SitemapFilters implements Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_filter('wp_sitemaps_posts_query_args', [$this, 'excludeNoindexed']);
        \add_filter('wp_sitemaps_post_types', [$this, 'excludeAttachments']);
        \add_filter('wp_sitemaps_taxonomies_entry', [$this, 'excludeThinTags'], 10, 2);
    }

    /**
     * @param array<string, \WP_Post_Type> $postTypes
     * @return array<string, \WP_Post_Type>
     */
    public function excludeAttachments(array $postTypes): array
    {
        if ($this->settings->get('redirect_attachment_pages', false)) {
            unset($postTypes['attachment']);
        }

        return $postTypes;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    public function excludeThinTags(array $entry, \WP_Term $term): array
    {
        if (! $this->settings->get('noindex_thin_archives', false)) {
            return $entry;
        }

        return $term->taxonomy === 'post_tag' && $term->count < HeadOutput::MIN_INDEXABLE_TAG_COUNT ? [] : $entry;
    }

    public function excludeNoindexed(array $args): array
    {
        // This is a query for what to KEEP, so it has to match posts
        // that are NOT noindexed — either no _scseo_noindex row at all,
        // or one that exists but isn't truthy. That second branch
        // matters: a stray postmeta row (e.g. left over from an import)
        // with an empty value reads as "not noindexed" everywhere else
        // in this plugin — SeoMeta::read() boolean-casts it to false —
        // so excluding on the row merely existing (the original bug)
        // silently dropped every such post from the sitemap while
        // nothing on the page itself said noindex.
        $args['meta_query'][] = [
            'relation' => 'OR',
            [
                'key' => '_scseo_noindex',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_scseo_noindex',
                'value' => '1',
                'compare' => '!=',
            ],
        ];

        return $args;
    }
}
