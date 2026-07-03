<?php
/**
 * Deactivation routine.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles cleanup of transient/temporary data on deactivation.
 */
final class Deactivator {

	/**
	 * Remove temporary data created by the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		delete_option( Database::STATE_OPTION );

		/**
		 * Fires during plugin deactivation so add-ons can clean up too.
		 */
		do_action( 'mcl_deactivate' );
	}
}
