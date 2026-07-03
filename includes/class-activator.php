<?php
/**
 * Activation routine.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles work that must run once on plugin activation.
 */
final class Activator {

	/**
	 * Create default options if they do not already exist.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( false === get_option( MCL_OPTION, false ) ) {
			add_option( MCL_OPTION, Helper::default_settings() );
		}

		/**
		 * Fires after the plugin has been activated so add-ons can run setup.
		 */
		do_action( 'mcl_activate' );
	}
}
