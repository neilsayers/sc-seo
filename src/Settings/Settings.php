<?php

namespace SCSEO\Settings;

/**
 * Reads/writes the plugin's single options-table row — every sitewide
 * default the head/schema output and the per-post metabox fall back
 * to. Same shape as SC Maps/SC Events Manager/SC Room Bookings' own
 * Settings classes, deliberately — but this plugin shares no code
 * with any of them.
 */
final class Settings
{
    private const OPTION_KEY = 'scseo_settings';

    /**
     * schema.org Organization subtypes worth offering — deliberately
     * not exhaustive (schema.org has dozens). LOCAL_TYPES is the
     * subset that implies a physical premises: picking one of those
     * reveals the address/phone/hours fields on the settings screen
     * and adds them to the Organization JSON-LD node; picking plain
     * Organization (the default — correct for a business with no
     * physical premises, e.g. this one) keeps the node to
     * name/logo/sameAs/contact only.
     */
    public const BUSINESS_TYPES = [
        'Organization' => 'Organization (no physical premises)',
        'LocalBusiness' => 'Local Business (generic)',
        'ProfessionalService' => 'Professional Service',
        'Store' => 'Store / Shop',
        'Restaurant' => 'Restaurant',
        'Corporation' => 'Corporation',
    ];

    private const LOCAL_TYPES = ['LocalBusiness', 'ProfessionalService', 'Store', 'Restaurant'];

    private const DEFAULTS = [
        'title_separator' => '–',
        'home_title' => '',
        'home_description' => '',
        'default_robots_noindex' => false,
        'disable_feeds' => false,
        'noindex_thin_archives' => false,
        'redirect_attachment_pages' => false,
        'default_social_image' => 0,
        'title_templates' => [],
        'ga_measurement_id' => '',
        'facebook_url' => '',
        'twitter_handle' => '',
        'instagram_url' => '',
        'linkedin_url' => '',
        'youtube_url' => '',
        'facebook_app_id' => '',
        'business_type' => 'Organization',
        'business_name' => '',
        'business_logo' => 0,
        'business_phone' => '',
        'business_email' => '',
        'business_address_street' => '',
        'business_address_locality' => '',
        'business_address_region' => '',
        'business_address_postcode' => '',
        'business_address_country' => '',
        'business_hours' => '',
    ];

    private array $values;

    public function __construct()
    {
        $stored = \get_option(self::OPTION_KEY, []);
        $this->values = \is_array($stored) ? \array_merge(self::DEFAULTS, $stored) : self::DEFAULTS;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function isLocalBusiness(): bool
    {
        return \in_array($this->get('business_type'), self::LOCAL_TYPES, true);
    }

    public function titleTemplate(string $postType): string
    {
        $templates = $this->get('title_templates', []);

        return $templates[$postType] ?? '%title% %sep% %sitename%';
    }

    /**
     * Every field is already sanitized by the caller (Admin\SettingsPage)
     * before this runs — this class just stores, it doesn't validate,
     * since the shape of "a valid value" differs per field (URL, email,
     * attachment ID, free text) and the settings form already has to
     * know that to build its inputs.
     *
     * @param array<string, mixed> $data
     */
    public function update(array $data): void
    {
        $this->values = \array_merge($this->values, $data);

        \update_option(self::OPTION_KEY, $this->values);
    }
}
