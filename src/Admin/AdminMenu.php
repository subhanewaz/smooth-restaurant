<?php
/**
 * Admin menu registration.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Admin;

/**
 * Class AdminMenu
 *
 * Registers the top-level admin menu and submenu pages for the plugin.
 */
class AdminMenu {

	/**
	 * The menu slug used for the top-level page.
	 *
	 * @var string
	 */
	private string $menu_slug = 'smooth-restaurant';

	/**
	 * Capability required to access the admin pages.
	 *
	 * @var string
	 */
	private string $capability = 'manage_options';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Add the top-level menu and submenu pages.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'Smooth Restaurant', 'smooth-restaurant' ),
			__( 'Smooth Restaurant', 'smooth-restaurant' ),
			$this->capability,
			$this->menu_slug,
			array( $this, 'render_admin_page' ),
			'dashicons-food',
			25
		);

		add_menu_page(
			__( 'About Us', 'smooth-restaurant' ),
			__( 'About Us', 'smooth-restaurant' ),
			$this->capability,
			'smooth-restaurant-about',
			array( $this, 'render_about_page' ),
			'dashicons-info',
			26
		);

		add_submenu_page(
			'smooth-restaurant-about',
			__( 'About Us', 'smooth-restaurant' ),
			__( 'About Us', 'smooth-restaurant' ),
			$this->capability,
			'smooth-restaurant-about',
			array( $this, 'render_about_page' )
		);
	}

	/**
	 * Render the admin page container.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		echo '<div id="smooth-restaurant-admin"></div>';
	}

	/**
	 * Render the About Us admin page container.
	 *
	 * @return void
	 */
	public function render_about_page(): void {
		echo '<div id="smooth-restaurant-admin"></div>';
	}
}
