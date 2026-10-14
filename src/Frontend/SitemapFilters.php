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
        \add_filter('wp_sitemaps_post_types', [$this, 'excludePostTypes']);
        \add_filter('wp_sitemaps_taxonomies_query_args', [$this, 'excludeThinTags'], 10, 2);
    }

    /**
     * @param array<string, \WP_Post_Type> $postTypes
     * @return array<string, \WP_Post_Type>
     */
    public function excludePostTypes(array $postTypes): array
    {
        if ($this->settings->get('redirect_attachment_pages', false)) {
            unset($postTypes['attachment']);
        }

        foreach ((array) $this->settings->get('sitemap_excluded_post_types', []) as $postType) {
            unset($postTypes[$postType]);
        }

        return $postTypes;
    }

    /**
     * Keeps tags the head output noindexes (see HeadOutput::
     * MIN_INDEXABLE_TAG_COUNT) out of the taxonomy sitemap.
     *
     * Done on the term query, not the per-entry filter: that one can't
     * drop an entry (returning [] still writes an empty <url> with no
     * <loc>), and it hands over the term ID, not a WP_Term. Excluding at
     * query level also keeps core's page count right, since it reads
     * the same arguments.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function excludeThinTags(array $args, string $taxonomy): array
    {
        if ($taxonomy !== 'post_tag' || ! $this->settings->get('noindex_thin_archives', false)) {
            return $args;
        }

        $thin = \get_terms([
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'fields' => 'all',
            'update_term_meta_cache' => false,
        ]);

        if (\is_wp_error($thin)) {
            return $args;
        }

        $ids = [];

        foreach ($thin as $term) {
            if ($term->count < HeadOutput::MIN_INDEXABLE_TAG_COUNT) {
                $ids[] = $term->term_id;
            }
        }

        if ($ids !== []) {
            $args['exclude'] = \array_merge((array) ($args['exclude'] ?? []), $ids);
        }

        return $args;
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
