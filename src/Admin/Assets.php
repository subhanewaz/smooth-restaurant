<?php
/**
 * Admin asset registration.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Admin;

/**
 * Class Assets
 *
 * Handles registration and enqueuing of admin-specific assets.
 */
class Assets {

	/**
	 * The menu slug used to identify plugin admin pages.
	 *
	 * @var string
	 */
	private string $menu_slug = 'smooth-restaurant';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_assets' ) );
	}

	/**
	 * Register and enqueue admin assets on plugin pages.
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function register_admin_assets( string $hook ): void {
		if ( ! $this->is_plugin_page( $hook ) ) {
			return;
		}

		$asset_file = SR_PLUGIN_DIR . 'assets/build/admin/index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => SR_VERSION,
			);

		$dependencies = array_merge(
			$asset['dependencies'],
			array( 'wp-api-fetch' )
		);

		wp_enqueue_script(
			'smooth-restaurant-admin',
			SR_PLUGIN_URL . 'assets/build/admin/index.js',
			$dependencies,
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'smooth-restaurant-admin',
			SR_PLUGIN_URL . 'assets/build/admin/style-index.css',
			array(),
			$asset['version']
		);

		$config = array(
			'restUrl'   => esc_url_raw( rest_url() ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
		);

		if ( 'toplevel_page_smooth-restaurant-about' === $hook ) {
			$config['initialRoute'] = '/about-us';
		}

		wp_localize_script(
			'smooth-restaurant-admin',
			'smoothRestaurantAdmin',
			$config
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				'smooth-restaurant-admin',
				'smooth-restaurant',
				SR_PLUGIN_DIR . 'languages'
			);
		}
	}

	/**
	 * Determine if the current admin page belongs to this plugin.
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return bool
	 */
	private function is_plugin_page( string $hook ): bool {
		return str_starts_with( $hook, 'toplevel_page_' . $this->menu_slug );
	}
}
