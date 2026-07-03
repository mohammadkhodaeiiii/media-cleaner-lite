<?php
/**
 * Storage abstraction.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists scan state, media index, references and reports using the Options API and Transients.
 *
 * A Pro version can extend this class (and be swapped in via the `mcl_storage` filter)
 * to persist the same data in custom database tables without changing any caller.
 */
class Database {

	/**
	 * Option key for the in-progress scan state.
	 */
	public const STATE_OPTION = 'mcl_scan_state';

	/**
	 * Option key for the last completed report.
	 */
	public const REPORT_OPTION = 'mcl_last_report';

	/**
	 * Option key for the media index.
	 */
	public const INDEX_OPTION = 'mcl_media_index';

	/**
	 * Option key for referenced attachment IDs.
	 */
	public const REFERENCES_OPTION = 'mcl_referenced_media';

	/**
	 * Option key for unused media results.
	 */
	public const UNUSED_OPTION = 'mcl_unused_media';

	/**
	 * Option key for delete operation log.
	 */
	public const DELETE_LOG_OPTION = 'mcl_delete_log';

	/**
	 * Option key prefix for per-attachment index records.
	 */
	public const ATTACHMENT_PREFIX = 'mcl_attachment_';

	/**
	 * Transient key for computed caches.
	 */
	public const CACHE_TRANSIENT = 'mcl_computed_cache';

	/**
	 * Retrieve the current scan state.
	 *
	 * @return array<string, mixed>
	 */
	public function get_state(): array {
		$state = get_option( self::STATE_OPTION, array() );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * Persist the scan state.
	 *
	 * @param array<string, mixed> $state Scan state.
	 * @return void
	 */
	public function save_state( array $state ): void {
		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * Delete the scan state.
	 *
	 * @return void
	 */
	public function delete_state(): void {
		delete_option( self::STATE_OPTION );
	}

	/**
	 * Whether a scan is currently in progress.
	 *
	 * @return bool
	 */
	public function has_active_scan(): bool {
		$state = $this->get_state();

		return isset( $state['status'] ) && in_array( $state['status'], array( 'running', 'paused' ), true );
	}

	/**
	 * Whether a scan is paused.
	 *
	 * @return bool
	 */
	public function is_paused(): bool {
		$state = $this->get_state();

		return isset( $state['status'] ) && 'paused' === $state['status'];
	}

	/**
	 * Retrieve the last completed report.
	 *
	 * @return array<string, mixed>
	 */
	public function get_report(): array {
		$report = get_option( self::REPORT_OPTION, array() );

		return is_array( $report ) ? $report : array();
	}

	/**
	 * Persist a completed report.
	 *
	 * @param array<string, mixed> $report Report data.
	 * @return void
	 */
	public function save_report( array $report ): void {
		update_option( self::REPORT_OPTION, $report, false );
	}

	/**
	 * Delete the stored report.
	 *
	 * @return void
	 */
	public function delete_report(): void {
		delete_option( self::REPORT_OPTION );
	}

	/**
	 * Persist the full media index.
	 *
	 * @param array<int, array<string, mixed>> $index Media index.
	 * @return void
	 */
	public function save_index( array $index ): void {
		update_option( self::INDEX_OPTION, $index, false );
	}

	/**
	 * Retrieve the media index.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_index(): array {
		$index = get_option( self::INDEX_OPTION, array() );

		return is_array( $index ) ? $index : array();
	}

	/**
	 * Delete the media index.
	 *
	 * @return void
	 */
	public function delete_index(): void {
		delete_option( self::INDEX_OPTION );
	}

	/**
	 * Persist referenced attachment map.
	 *
	 * @param array<int, array<string, mixed>> $references Reference map keyed by attachment ID.
	 * @return void
	 */
	public function save_references( array $references ): void {
		update_option( self::REFERENCES_OPTION, $references, false );
	}

	/**
	 * Retrieve referenced attachment map.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_references(): array {
		$references = get_option( self::REFERENCES_OPTION, array() );

		return is_array( $references ) ? $references : array();
	}

	/**
	 * Delete referenced attachment data.
	 *
	 * @return void
	 */
	public function delete_references(): void {
		delete_option( self::REFERENCES_OPTION );
	}

	/**
	 * Persist unused media results.
	 *
	 * @param array<int, array<string, mixed>> $unused Unused media list.
	 * @return void
	 */
	public function save_unused( array $unused ): void {
		update_option( self::UNUSED_OPTION, $unused, false );
	}

	/**
	 * Retrieve unused media results.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_unused(): array {
		$unused = get_option( self::UNUSED_OPTION, array() );

		return is_array( $unused ) ? $unused : array();
	}

	/**
	 * Delete unused media results.
	 *
	 * @return void
	 */
	public function delete_unused(): void {
		delete_option( self::UNUSED_OPTION );
	}

	/**
	 * Append an entry to the delete log.
	 *
	 * @param array<string, mixed> $entry Log entry.
	 * @return void
	 */
	public function append_delete_log( array $entry ): void {
		$log   = $this->get_delete_log();
		$log[] = $entry;

		if ( count( $log ) > 500 ) {
			$log = array_slice( $log, -500 );
		}

		update_option( self::DELETE_LOG_OPTION, $log, false );
	}

	/**
	 * Retrieve the delete log.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_delete_log(): array {
		$log = get_option( self::DELETE_LOG_OPTION, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Retrieve a cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed|null
	 */
	public function get_cache( string $key ): mixed {
		$cache = get_transient( self::CACHE_TRANSIENT );

		if ( ! is_array( $cache ) || ! array_key_exists( $key, $cache ) ) {
			return null;
		}

		return $cache[ $key ];
	}

	/**
	 * Persist a cached value.
	 *
	 * @param string $key      Cache key.
	 * @param mixed  $value    Value to store.
	 * @param int    $lifetime Lifetime in seconds.
	 * @return void
	 */
	public function set_cache( string $key, mixed $value, int $lifetime ): void {
		$cache         = get_transient( self::CACHE_TRANSIENT );
		$cache         = is_array( $cache ) ? $cache : array();
		$cache[ $key ] = $value;

		set_transient( self::CACHE_TRANSIENT, $cache, max( 0, $lifetime ) );
	}

	/**
	 * Clear all cached data.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		delete_transient( self::CACHE_TRANSIENT );
	}

	/**
	 * Remove all plugin data created during scanning.
	 *
	 * @return void
	 */
	public function purge_scan_data(): void {
		$this->delete_state();
		$this->delete_report();
		$this->delete_index();
		$this->delete_references();
		$this->delete_unused();
		$this->clear_cache();
	}
}
