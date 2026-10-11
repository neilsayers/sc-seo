<?php

namespace SCSEO\Network;

/**
 * The multisite-only layer: one network-wide switch, plus the options
 * it unlocks. Stored as a site option (shared by every subsite) and
 * edited from the main site's SC SEO → Network tab.
 *
 * Everything here is inert unless the switch is on — on a single site,
 * or on a network where it hasn't been turned on, isActive() is false
 * and no other class changes its behaviour. Consumers should gate on
 * isActive() rather than reading the raw values.
 */
final class NetworkSettings
{
    private const OPTION_KEY = 'scseo_network';

    private const DEFAULTS = [
        'enabled' => false,
        'ga_measurement_id' => '',
    ];

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function isActive(): bool
    {
        return \is_multisite() && (bool) $this->get('enabled');
    }

    /**
     * Whether the current request is for the network's main site — the
     * only place the Network tab is shown.
     */
    public function isMainSite(): bool
    {
        return \is_multisite() && \is_main_site();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->load()[$key] ?? $default;
    }

    /**
     * Network-wide GA4 Measurement ID that every subsite also reports
     * to, or '' when the network layer is off or no ID is set.
     */
    public function gaMeasurementId(): string
    {
        return $this->isActive() ? (string) $this->get('ga_measurement_id') : '';
    }

    /**
     * @param array<string, mixed> $data already sanitized by the caller
     */
    public function update(array $data): void
    {
        $this->values = \array_merge($this->load(), $data);

        \update_site_option(self::OPTION_KEY, $this->values);
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        if ($this->values === null) {
            $stored = \is_multisite() ? \get_site_option(self::OPTION_KEY, []) : [];
            $this->values = \is_array($stored) ? \array_merge(self::DEFAULTS, $stored) : self::DEFAULTS;
        }

        return $this->values;
    }
}
