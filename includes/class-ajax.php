<?php
/**
 * AJAX endpoints.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

use MediaCleanerLite\Interfaces\ServiceInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Secure AJAX endpoints for scanning, deletion and cache management.
 */
final class Ajax implements ServiceInterface {

	/**
	 * Nonce action.
	 */
	public const NONCE_ACTION = 'mcl_scan';

	/**
	 * Start scan action.
	 */
	public const ACTION_START = 'mcl_start_scan';

	/**
	 * Continue scan action.
	 */
	public const ACTION_CONTINUE = 'mcl_continue_scan';

	/**
	 * Pause scan action.
	 */
	public const ACTION_PAUSE = 'mcl_pause_scan';

	/**
	 * Cancel scan action.
	 */
	public const ACTION_CANCEL = 'mcl_cancel_scan';

	/**
	 * Clear cache action.
	 */
	public const ACTION_CLEAR = 'mcl_clear_cache';

	/**
	 * Refresh report action.
	 */
	public const ACTION_REFRESH = 'mcl_refresh_report';

	/**
	 * Delete media action.
	 */
	public const ACTION_DELETE = 'mcl_delete_media';

	/**
	 * Restore media action.
	 */
	public const ACTION_RESTORE = 'mcl_restore_media';

	/**
	 * Required capability.
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Shared hook loader.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Scanner.
	 *
	 * @var Scanner
	 */
	private Scanner $scanner;

	/**
	 * Reporter.
	 *
	 * @var Reporter
	 */
	private Reporter $reporter;

	/**
	 * Storage abstraction.
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * Safe delete service.
	 *
	 * @var SafeDelete
	 */
	private SafeDelete $safe_delete;

	/**
	 * Cache layer.
	 *
	 * @var Cache
	 */
	private Cache $cache;

	/**
	 * Constructor.
	 *
	 * @param Loader     $loader      Shared hook loader.
	 * @param Scanner    $scanner     Scanner.
	 * @param Reporter   $reporter    Reporter.
	 * @param Database   $database    Storage abstraction.
	 * @param SafeDelete $safe_delete Safe delete service.
	 * @param Cache      $cache       Cache layer.
	 */
	public function __construct( Loader $loader, Scanner $scanner, Reporter $reporter, Database $database, SafeDelete $safe_delete, Cache $cache ) {
		$this->loader      = $loader;
		$this->scanner     = $scanner;
		$this->reporter    = $reporter;
		$this->database    = $database;
		$this->safe_delete = $safe_delete;
		$this->cache       = $cache;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->loader->add_action( 'wp_ajax_' . self::ACTION_START, $this, 'start' );
		$this->loader->add_action( 'wp_ajax_' . self::ACTION_CONTINUE, $this, 'continue_scan' );
		$this->loader->add_action( 'wp_ajax_' . self::ACTION_PAUSE, $this, 'pause' );
		$this->loader->add_action( 'wp_ajax_' . self::ACTION_CANCEL, $this, 'cancel' );
		$this->loader->add_action( 'wp_ajax_' . self::ACTION_CLEAR, $this, 'clear_cache' );
		$this->loader->add_action( 'wp_ajax_' . self::ACTION_REFRESH, $this, 'refresh' );
		$this->loader->add_action( 'wp_ajax_' . self::ACTION_DELETE, $this, 'delete_media' );
		$this->loader->add_action( 'wp_ajax_' . self::ACTION_RESTORE, $this, 'restore_media' );
	}

	/**
	 * Start a new scan.
	 *
	 * @return void
	 */
	public function start(): void {
		$this->guard();

		if ( ! Helper::to_bool( Helper::get_setting( 'enabled', true ) ) ) {
			wp_send_json_error(
				array( 'message' => __( 'اسکنر در تنظیمات غیرفعال است.', 'media-cleaner-lite' ) ),
				403
			);
		}

		wp_send_json_success( $this->scanner->start() );
	}

	/**
	 * Continue or resume the current scan.
	 *
	 * @return void
	 */
	public function continue_scan(): void {
		$this->guard();

		if ( $this->database->is_paused() ) {
			wp_send_json_success( $this->scanner->resume() );
		}

		wp_send_json_success( $this->scanner->process_batch() );
	}

	/**
	 * Pause the current scan.
	 *
	 * @return void
	 */
	public function pause(): void {
		$this->guard();

		wp_send_json_success( $this->scanner->pause() );
	}

	/**
	 * Cancel the current scan.
	 *
	 * @return void
	 */
	public function cancel(): void {
		$this->guard();

		wp_send_json_success( $this->scanner->cancel() );
	}

	/**
	 * Clear cached data.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->guard();

		$this->cache->flush();

		wp_send_json_success(
			array( 'message' => __( 'کش پاک شد.', 'media-cleaner-lite' ) )
		);
	}

	/**
	 * Return the latest report.
	 *
	 * @return void
	 */
	public function refresh(): void {
		$this->guard();

		wp_send_json_success(
			array(
				'stats'  => $this->reporter->get_last_report(),
				'unused' => $this->database->get_unused(),
			)
		);
	}

	/**
	 * Delete unused media safely.
	 *
	 * @return void
	 */
	public function delete_media(): void {
		$this->guard();

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		$force         = isset( $_POST['force'] ) ? Helper::to_bool( wp_unslash( $_POST['force'] ) ) : false;

		$result = $this->safe_delete->delete( $attachment_id, $force );

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $result['message'] ?? '' ) ), 400 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Restore trashed media.
	 *
	 * @return void
	 */
	public function restore_media(): void {
		$this->guard();

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		$result        = $this->safe_delete->restore( $attachment_id );

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $result['message'] ?? '' ) ), 400 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Verify nonce and capability.
	 *
	 * @return void
	 */
	private function guard(): void {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'بررسی امنیتی ناموفق بود.', 'media-cleaner-lite' ) ),
				403
			);
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error(
				array( 'message' => __( 'شما اجازه انجام این کار را ندارید.', 'media-cleaner-lite' ) ),
				403
			);
		}
	}
}
