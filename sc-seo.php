<?php

/**
 * Plugin Name:       SC SEO
 * Plugin URI:        https://screencandy.co.uk
 * Description:       Lean, site-agnostic technical SEO — titles, meta descriptions, Open Graph/Twitter cards, canonical URLs, robots controls, schema.org JSON-LD, and a 301/302 redirect manager. No content-analysis bloat.
 * Version:           0.2.1
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Neil Sayers
 * Author URI:        https://screencandy.co.uk
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sc-seo
 */

namespace SCSEO;

if (! defined('ABSPATH')) {
    exit;
}

define('SCSEO_VERSION', '0.2.1');
define('SCSEO_FILE', __FILE__);
define('SCSEO_PATH', \plugin_dir_path(__FILE__));
define('SCSEO_URL', \plugin_dir_url(__FILE__));

/**
 * Minimal PSR-4-style autoloader so this plugin has zero build step
 * or Composer dependency — it just needs to be copied into any site's
 * wp-content/plugins and activated. Same shape as SC Events Manager's,
 * SC Room Bookings' and SC Maps' own autoloaders, deliberately — but
 * this plugin shares no code with any of them at runtime. Any subset
 * of the four can be active on a site at once.
 */
\spl_autoload_register(function (string $class): void {
    $prefix = __NAMESPACE__.'\\';

    if (! \str_starts_with($class, $prefix)) {
        return;
    }

    $relative = \substr($class, \strlen($prefix));
    $path = SCSEO_PATH.'src/'.\str_replace('\\', '/', $relative).'.php';

    if (\is_file($path)) {
        require $path;
    }
});

\register_activation_hook(__FILE__, [Setup\Activator::class, 'activate']);
\register_deactivation_hook(__FILE__, [Setup\Activator::class, 'deactivate']);

Plugin::instance()->boot();
