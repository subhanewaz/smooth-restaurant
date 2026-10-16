<?php
/**
 * Plugin Name: Smooth Restaurant
 * Plugin URI:  https://smoothrestaurant.com
 * Description: The restaurant management plugin that actually works for restaurant people.
 * Version:     0.1.0
 * Author:      Smooth Restaurant
 * Author URI:  https://smoothplugins.com
 * License:     GPL-2.0
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: smooth-restaurant
 * Domain Path: /languages
 * Requires PHP: 8.2
 * Requires at least: 6.8
 *
 * @package SmoothRestaurant
 */

if (!defined("ABSPATH")) {
	exit();
}

define("SR_VERSION", "0.1.0");
define("SR_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("SR_PLUGIN_URL", plugin_dir_url(__FILE__));
define("SR_PLUGIN_BASENAME", plugin_basename(__FILE__));

// Minimum PHP version check
if (version_compare(PHP_VERSION, "8.2", "<")) {
	add_action("admin_notices", function (): void {
		echo '<div class="notice notice-error"><p>';
		printf(
			esc_html__(
				"Smooth Restaurant requires PHP 8.2 or higher. You are running %s.",
				"smooth-restaurant",
			),
			esc_html(PHP_VERSION),
		);
		echo "</p></div>";
	});
	return;
}

// Autoloader
spl_autoload_register(function (string $class): void {
	$prefix = "SmoothRestaurant\\";
	$base_dir = SR_PLUGIN_DIR . "src/";

	$len = strlen($prefix);
	if (strncmp($prefix, $class, $len) !== 0) {
		return;
	}

	$relative_class = substr($class, $len);
	$file = $base_dir . str_replace("\\", "/", $relative_class) . ".php";

	if (file_exists($file)) {
		require $file;
	}
});

// About Us module
require_once SR_PLUGIN_DIR . 'about-us/about-us.php';

// Activation hook
register_activation_hook(__FILE__, [\SmoothRestaurant\Core\Activator::class, 'activate']);

// Deactivation hook
register_deactivation_hook(__FILE__, [\SmoothRestaurant\Core\Deactivator::class, 'deactivate']);

// Boot the plugin
add_action(
	"plugins_loaded",
	function (): void {
		\SmoothRestaurant\Core\Plugin::instance()->boot();
	},
	10,
);
