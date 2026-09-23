<?php

namespace SCSEO\Frontend;

use SCSEO\Contracts\Hookable;
use SCSEO\Settings\Settings;
use SCSEO\Support\SeoMeta;

/**
 * Title, meta description, robots, canonical, Open Graph and Twitter
 * Card tags — everything a page's <head> needs for search and social
 * sharing, generated from Settings\Settings' sitewide defaults and
 * Support\SeoMeta's per-post overrides. Deliberately covers singular
 * posts/pages/CPTs and the front page only for v1 — search/404 keep
 * WordPress core's own perfectly reasonable defaults rather than this
 * plugin guessing at something to override them with.
 *
 * Archives are the one exception, and only for the three shapes this
 * site actually produces that are guaranteed near-duplicates of
 * something else already indexed: the sole author's archive (identical
 * to the blog itself), date archives (identical to whichever posts they
 * list), and a tag archive too thin to be a listing at all. Search
 * Console calling these "crawled/discovered — currently not indexed"
 * isn't a problem to fix, it's Google correctly declining pages that
 * were never worth a slot — noindex just says so up front instead of
 * leaving Google to work it out per page.
 */
final class HeadOutput implements Hookable
{
    /**
     * A tag archive listing fewer posts than this is a near-duplicate
     * of the one post it lists (or an empty page, for zero) rather than
     * a listing — see the class docblock. Also read by SitemapFilters,
     * so a tag excluded here is excluded from the sitemap too.
     */
    public const MIN_INDEXABLE_TAG_COUNT = 2;

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        // WordPress prints its own <link rel="canonical"> via this
        // callback — remove it so ours (which can be overridden per
        // post) is the only one on the page.
        \remove_action('wp_head', 'rel_canonical');

        \add_filter('pre_get_document_title', [$this, 'filterTitle']);
        \add_filter('wp_robots', [$this, 'filterRobots']);
        \add_action('wp_head', [$this, 'renderHead'], 1);
    }

    private function queriedPost(): ?\WP_Post
    {
        // The posts page (Settings → Reading) is an archive, not a singular
        // view, but it's a real Page with its own SEO fields — treat it as one.
        if (\is_home() && ! \is_front_page()) {
            $postsPage = (int) \get_option('page_for_posts');
            $post = $postsPage ? \get_post($postsPage) : null;

            return $post instanceof \WP_Post ? $post : null;
        }

        if (! \is_singular()) {
            return null;
        }

        $post = \get_queried_object();

        return $post instanceof \WP_Post ? $post : null;
    }

    public function filterTitle(string $title): string
    {
        $post = $this->queriedPost();

        if ($post === null) {
            return $title;
        }

        $meta = SeoMeta::read($post->ID);

        if ($meta['title'] !== '') {
            return $meta['title'];
        }

        if (\is_front_page() && $this->settings->get('home_title') !== '') {
            return $this->resolveTemplate((string) $this->settings->get('home_title'), $post);
        }

        return $this->resolveTemplate($this->settings->titleTemplate($post->post_type), $post);
    }

    private function resolveTemplate(string $template, \WP_Post $post): string
    {
        $replacements = [
            '%title%' => $post->post_title,
            '%sitename%' => \get_bloginfo('name'),
            '%sep%' => (string) $this->settings->get('title_separator', '–'),
            '%excerpt%' => \wp_strip_all_tags(\get_the_excerpt($post)),
        ];

        return \trim(\strtr($template, $replacements));
    }

    private function resolveDescription(\WP_Post $post, array $meta): string
    {
        if ($meta['description'] !== '') {
            return $meta['description'];
        }

        if (\is_front_page() && $this->settings->get('home_description') !== '') {
            return (string) $this->settings->get('home_description');
        }

        $excerpt = \wp_strip_all_tags(\get_the_excerpt($post));

        return $excerpt !== '' ? \wp_trim_words($excerpt, 40, '…') : '';
    }

    private function resolveCanonical(\WP_Post $post, array $meta): string
    {
        if ($meta['canonical'] !== '') {
            return $meta['canonical'];
        }

        return \is_front_page() ? \home_url('/') : \get_permalink($post);
    }

    /**
     * @return array{url: string, width: int, height: int}|null
     */
    private function resolveImage(\WP_Post $post, array $meta): ?array
    {
        $attachmentId = $meta['og_image'] ?: (int) \get_post_thumbnail_id($post);

        if (! $attachmentId) {
            $attachmentId = (int) $this->settings->get('default_social_image', 0);
        }

        if (! $attachmentId) {
            return null;
        }

        $src = \wp_get_attachment_image_src($attachmentId, 'large');

        if (! $src) {
            return null;
        }

        return ['url' => $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2]];
    }

    /**
     * Whether the archive being requested is one of the three
     * near-duplicate shapes covered by the "Noindex thin archives"
     * setting — see the class docblock. Gated on that setting so
     * enabling it is a deliberate choice per site, not a behaviour
     * change every site sharing this plugin inherits silently.
     */
    private function isThinArchive(): bool
    {
        if (! $this->settings->get('noindex_thin_archives', false)) {
            return false;
        }

        if (\is_author() || \is_date()) {
            return true;
        }

        if (! \is_tag()) {
            return false;
        }

        $term = \get_queried_object();

        return $term instanceof \WP_Term && $term->count < self::MIN_INDEXABLE_TAG_COUNT;
    }

    public function filterRobots(array $robots): array
    {
        $noindex = (bool) $this->settings->get('default_robots_noindex', false);
        $nofollow = false;

        $post = $this->queriedPost();

        if ($post !== null) {
            $meta = SeoMeta::read($post->ID);
            $noindex = $noindex || $meta['noindex'];
            $nofollow = $meta['nofollow'];
        }

        if ($this->isThinArchive()) {
            $noindex = true;
        }

        if ($noindex) {
            unset($robots['index']);
            $robots['noindex'] = true;
        }

        if ($nofollow) {
            unset($robots['follow']);
            $robots['nofollow'] = true;
        }

        return $robots;
    }

    public function renderHead(): void
    {
        $post = $this->queriedPost();

        if ($post === null) {
            return;
        }

        $meta = SeoMeta::read($post->ID);
        $description = $this->resolveDescription($post, $meta);
        $canonical = $this->resolveCanonical($post, $meta);
        $ogTitle = $meta['og_title'] !== '' ? $meta['og_title'] : $this->filterTitle($post->post_title);
        $ogDescription = $meta['og_description'] !== '' ? $meta['og_description'] : $description;
        $image = $this->resolveImage($post, $meta);

        if ($description !== '') {
            printf('<meta name="description" content="%s">'."\n", \esc_attr($description));
        }

        printf('<link rel="canonical" href="%s">'."\n", \esc_url($canonical));

        printf('<meta property="og:type" content="%s">'."\n", \esc_attr(\is_front_page() ? 'website' : 'article'));
        printf('<meta property="og:title" content="%s">'."\n", \esc_attr($ogTitle));

        if ($ogDescription !== '') {
            printf('<meta property="og:description" content="%s">'."\n", \esc_attr($ogDescription));
        }

        printf('<meta property="og:url" content="%s">'."\n", \esc_url($canonical));
        printf('<meta property="og:site_name" content="%s">'."\n", \esc_attr(\get_bloginfo('name')));
        printf('<meta property="og:locale" content="%s">'."\n", \esc_attr(\get_locale()));

        if ($image !== null) {
            printf('<meta property="og:image" content="%s">'."\n", \esc_url($image['url']));
            printf('<meta property="og:image:width" content="%d">'."\n", $image['width']);
            printf('<meta property="og:image:height" content="%d">'."\n", $image['height']);
        }

        printf('<meta name="twitter:card" content="%s">'."\n", $image !== null ? 'summary_large_image' : 'summary');
        printf('<meta name="twitter:title" content="%s">'."\n", \esc_attr($ogTitle));

        if ($ogDescription !== '') {
            printf('<meta name="twitter:description" content="%s">'."\n", \esc_attr($ogDescription));
        }

        if ($image !== null) {
            printf('<meta name="twitter:image" content="%s">'."\n", \esc_url($image['url']));
        }

        $twitterHandle = (string) $this->settings->get('twitter_handle');

        if ($twitterHandle !== '') {
            printf('<meta name="twitter:site" content="@%s">'."\n", \esc_attr(\ltrim($twitterHandle, '@')));
        }

        $facebookAppId = (string) $this->settings->get('facebook_app_id');

        if ($facebookAppId !== '') {
            printf('<meta property="fb:app_id" content="%s">'."\n", \esc_attr($facebookAppId));
        }
    }
}
