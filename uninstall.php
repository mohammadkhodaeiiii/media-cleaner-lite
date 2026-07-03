<?php
/**
 * Uninstall routine.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all plugin data for the current site.
 *
 * @return void
 */
function mcl_uninstall_site(): void {
	delete_option( 'mcl_settings' );
	delete_option( 'mcl_scan_state' );
	delete_option( 'mcl_last_report' );
	delete_option( 'mcl_media_index' );
	delete_option( 'mcl_referenced_media' );
	delete_option( 'mcl_unused_media' );
	delete_option( 'mcl_delete_log' );
	delete_transient( 'mcl_computed_cache' );
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		mcl_uninstall_site();
		restore_current_blog();
	}

	delete_site_option( 'mcl_settings' );
} else {
	mcl_uninstall_site();
}
