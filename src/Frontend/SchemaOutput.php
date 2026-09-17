<?php

namespace SCSEO\Frontend;

use SCSEO\Contracts\Hookable;
use SCSEO\Settings\Settings;
use SCSEO\Support\SeoMeta;

/**
 * One JSON-LD "@graph" block per page rather than several separate
 * <script> tags: an Organization/LocalBusiness node and a WebSite
 * node on every page (this is what actually helps a Knowledge Panel
 * or an AI answer engine understand *who the site belongs to* —
 * arguably more valuable for that purpose than any per-page markup),
 * plus a BreadcrumbList and a page-level node (Article/WebPage/...)
 * on singular content.
 *
 * The assembled graph is run through the `scseo_schema_graph` filter
 * before output — this is the extension point other SC plugins (SC
 * Events Manager, SC Room Bookings) can hook into later to add their
 * own nodes (Event, Product-ish room/offer data) without SC SEO
 * needing to know either plugin exists.
 */
final class SchemaOutput implements Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('wp_head', [$this, 'renderSchema'], 5);
    }

    public function renderSchema(): void
    {
        $graph = [$this->organizationNode(), $this->websiteNode()];
        $post = null;

        if (\is_singular()) {
            $queried = \get_queried_object();
            $post = $queried instanceof \WP_Post ? $queried : null;
        }

        if ($post !== null) {
            $meta = SeoMeta::read($post->ID);

            if ($meta['schema_type'] !== 'none') {
                $breadcrumb = $this->breadcrumbNode($post);

                if ($breadcrumb !== null) {
                    $graph[] = $breadcrumb;
                }

                $graph[] = $this->pageNode($post, $meta, $breadcrumb !== null);
            }
        }

        $graph = \apply_filters('scseo_schema_graph', $graph, $post);

        if (empty($graph)) {
            return;
        }

        printf(
            '<script type="application/ld+json">%s</script>'."\n",
            \wp_json_encode(['@context' => 'https://schema.org', '@graph' => \array_values($graph)], \JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationNode(): array
    {
        $node = [
            '@type' => $this->settings->get('business_type', 'Organization'),
            '@id' => \home_url('/#organization'),
            'name' => $this->settings->get('business_name') ?: \get_bloginfo('name'),
            'url' => \home_url('/'),
        ];

        $logoId = (int) $this->settings->get('business_logo', 0);

        if ($logoId) {
            $logo = $this->imageObject($logoId);

            if ($logo !== null) {
                $node['logo'] = $logo;
                $node['image'] = $logo;
            }
        }

        $sameAs = \array_filter([
            (string) $this->settings->get('facebook_url'),
            (string) $this->settings->get('instagram_url'),
            (string) $this->settings->get('linkedin_url'),
            (string) $this->settings->get('youtube_url'),
            $this->twitterProfileUrl(),
        ]);

        if ($sameAs !== []) {
            $node['sameAs'] = \array_values($sameAs);
        }

        $phone = (string) $this->settings->get('business_phone');
        $email = (string) $this->settings->get('business_email');

        if ($phone !== '' || $email !== '') {
            $node['contactPoint'] = \array_filter([
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'telephone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
            ]);
        }

        if ($this->settings->isLocalBusiness()) {
            $address = \array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => (string) $this->settings->get('business_address_street') ?: null,
                'addressLocality' => (string) $this->settings->get('business_address_locality') ?: null,
                'addressRegion' => (string) $this->settings->get('business_address_region') ?: null,
                'postalCode' => (string) $this->settings->get('business_address_postcode') ?: null,
                'addressCountry' => (string) $this->settings->get('business_address_country') ?: null,
            ]);

            if ($address !== []) {
                $node['address'] = $address;
            }

            $hours = \array_values(\array_filter(\array_map('trim', \explode("\n", (string) $this->settings->get('business_hours')))));

            if ($hours !== []) {
                $node['openingHours'] = $hours;
            }
        }

        return $node;
    }

    private function twitterProfileUrl(): string
    {
        $handle = \ltrim((string) $this->settings->get('twitter_handle'), '@');

        return $handle !== '' ? 'https://twitter.com/'.$handle : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function websiteNode(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => \home_url('/#website'),
            'url' => \home_url('/'),
            'name' => \get_bloginfo('name'),
            'publisher' => ['@id' => \home_url('/#organization')],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function breadcrumbNode(\WP_Post $post): ?array
    {
        // The front page's own crumb *is* "Home" — a second "Home >
        // Home" entry for it would be a visibly broken duplicate, so
        // there's simply no breadcrumb trail worth printing there.
        if (\is_front_page()) {
            return null;
        }

        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => \home_url('/')]];
        $ancestors = \array_reverse(\get_post_ancestors($post));

        foreach ($ancestors as $ancestorId) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => \count($items) + 1,
                'name' => \get_the_title($ancestorId),
                'item' => \get_permalink($ancestorId),
            ];
        }

        $items[] = [
            '@type' => 'ListItem',
            'position' => \count($items) + 1,
            'name' => \get_the_title($post),
            'item' => \get_permalink($post),
        ];

        if (\count($items) < 2) {
            return null;
        }

        return [
            '@type' => 'BreadcrumbList',
            '@id' => \get_permalink($post).'#breadcrumb',
            'itemListElement' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function pageNode(\WP_Post $post, array $meta, bool $hasBreadcrumb): array
    {
        $type = ($meta['schema_type'] !== '' && $meta['schema_type'] !== 'auto')
            ? $meta['schema_type']
            : ($post->post_type === 'post' ? 'Article' : 'WebPage');

        $node = [
            '@type' => $type,
            '@id' => \get_permalink($post).'#'.\strtolower($type),
            'url' => \get_permalink($post),
            'name' => \get_the_title($post),
            'isPartOf' => ['@id' => \home_url('/#website')],
        ];

        if ($hasBreadcrumb) {
            $node['breadcrumb'] = ['@id' => \get_permalink($post).'#breadcrumb'];
        }

        $description = $meta['description'] !== '' ? $meta['description'] : \wp_strip_all_tags(\get_the_excerpt($post));

        if ($description !== '') {
            $node['description'] = $description;
        }

        $imageId = $meta['og_image'] ?: (int) \get_post_thumbnail_id($post);
        $image = $imageId ? $this->imageObject($imageId) : null;

        if ($image !== null) {
            $node['image'] = $image;
        }

        if ($type === 'Article') {
            $node['headline'] = \get_the_title($post);
            $node['datePublished'] = \get_the_date('c', $post);
            $node['dateModified'] = \get_the_modified_date('c', $post);
            $node['author'] = [
                '@type' => 'Person',
                'name' => \get_the_author_meta('display_name', (int) $post->post_author),
            ];
            $node['publisher'] = ['@id' => \home_url('/#organization')];
        }

        return $node;
    }

    /**
     * @return array{'@type': string, url: string, width: int, height: int}|null
     */
    private function imageObject(int $attachmentId): ?array
    {
        $src = \wp_get_attachment_image_src($attachmentId, 'large');

        if (! $src) {
            return null;
        }

        return ['@type' => 'ImageObject', 'url' => $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2]];
    }
}
