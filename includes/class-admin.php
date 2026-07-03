<?php
/**
 * Admin menus and page rendering.
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
 * Registers the native admin menu and renders plugin views.
 */
final class Admin implements ServiceInterface {

	/**
	 * Top-level menu slug.
	 */
	public const MENU_SLUG = 'media-cleaner-lite';

	/**
	 * Scanner page slug.
	 */
	public const SCANNER_SLUG = 'media-cleaner-lite-scanner';

	/**
	 * Unused media page slug.
	 */
	public const UNUSED_SLUG = 'media-cleaner-lite-unused';

	/**
	 * Reports page slug.
	 */
	public const REPORTS_SLUG = 'media-cleaner-lite-reports';

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
	 * Constructor.
	 *
	 * @param Loader   $loader   Shared hook loader.
	 * @param Reporter $reporter Reporter.
	 * @param Database $database Storage abstraction.
	 */
	public function __construct( Loader $loader, Reporter $reporter, Database $database ) {
		$this->loader   = $loader;
		$this->reporter = $reporter;
		$this->database = $database;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->loader->add_action( 'admin_menu', $this, 'register_menu' );
	}

	/**
	 * Register admin menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'پاک‌سازی رسانه', 'media-cleaner-lite' ),
			__( 'پاک‌سازی رسانه', 'media-cleaner-lite' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-images-alt2',
			83
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'پیشخوان', 'media-cleaner-lite' ),
			__( 'پیشخوان', 'media-cleaner-lite' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'اسکنر', 'media-cleaner-lite' ),
			__( 'اسکنر', 'media-cleaner-lite' ),
			self::CAPABILITY,
			self::SCANNER_SLUG,
			array( $this, 'render_scanner' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'رسانه استفاده‌نشده', 'media-cleaner-lite' ),
			__( 'رسانه استفاده‌نشده', 'media-cleaner-lite' ),
			self::CAPABILITY,
			self::UNUSED_SLUG,
			array( $this, 'render_unused' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'گزارش‌ها', 'media-cleaner-lite' ),
			__( 'گزارش‌ها', 'media-cleaner-lite' ),
			self::CAPABILITY,
			self::REPORTS_SLUG,
			array( $this, 'render_reports' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'تنظیمات', 'media-cleaner-lite' ),
			__( 'تنظیمات', 'media-cleaner-lite' ),
			self::CAPABILITY,
			Settings::PAGE,
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Render the Dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		$this->guard();

		$report          = $this->reporter->get_last_report();
		$has_active_scan = $this->database->has_active_scan();
		$is_paused       = $this->database->is_paused();
		$enabled         = Helper::to_bool( Helper::get_setting( 'enabled', true ) );
		$scanner_url     = $this->page_url( self::SCANNER_SLUG );
		$unused_url      = $this->page_url( self::UNUSED_SLUG );
		$reports_url     = $this->page_url( self::REPORTS_SLUG );
		$settings_url    = $this->page_url( Settings::PAGE );

		$this->view(
			'dashboard',
			compact( 'report', 'has_active_scan', 'is_paused', 'enabled', 'scanner_url', 'unused_url', 'reports_url', 'settings_url' )
		);
	}

	/**
	 * Render the Scanner page.
	 *
	 * @return void
	 */
	public function render_scanner(): void {
		$this->guard();

		$has_active_scan = $this->database->has_active_scan();
		$is_paused       = $this->database->is_paused();
		$enabled         = Helper::to_bool( Helper::get_setting( 'enabled', true ) );
		$post_types      = Helper::sanitize_post_types( Helper::get_setting( 'post_types', array() ) );
		$can_scan        = $enabled && ! empty( $post_types );

		$this->view( 'scanner', compact( 'has_active_scan', 'is_paused', 'enabled', 'post_types', 'can_scan' ) );
	}

	/**
	 * Render the Unused Media page.
	 *
	 * @return void
	 */
	public function render_unused(): void {
		$this->guard();

		$unused = $this->database->get_unused();
		$status = isset( $_GET['mcl_status'] ) ? sanitize_key( wp_unslash( $_GET['mcl_status'] ) ) : 'unused'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'all' !== $status ) {
			$unused = array_values(
				array_filter(
					$unused,
					static function ( $item ) use ( $status ): bool {
						return is_array( $item ) && ( $item['status'] ?? '' ) === $status;
					}
				)
			);
		} else {
			$unused = array_values( $unused );
		}

		$search = isset( $_GET['mcl_search'] ) ? sanitize_text_field( wp_unslash( $_GET['mcl_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' !== $search ) {
			$needle = strtolower( $search );
			$unused = array_values(
				array_filter(
					$unused,
					static function ( $item ) use ( $needle ): bool {
						if ( ! is_array( $item ) ) {
							return false;
						}
						$haystack = strtolower(
							(string) ( $item['filename'] ?? '' ) . ' ' .
							(string) ( $item['title'] ?? '' ) . ' ' .
							(string) ( $item['mime_type'] ?? '' )
						);
						return false !== strpos( $haystack, $needle );
					}
				)
			);
		}

		$this->view( 'unused-media', compact( 'unused', 'status', 'search' ) );
	}

	/**
	 * Render the Reports page.
	 *
	 * @return void
	 */
	public function render_reports(): void {
		$this->guard();

		$report        = $this->reporter->get_last_report();
		$dashboard_url = $this->page_url( self::MENU_SLUG );
		$delete_log    = $this->database->get_delete_log();

		$this->view( 'reports', compact( 'report', 'dashboard_url', 'delete_log' ) );
	}

	/**
	 * Render the Settings page.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		$this->guard();

		$reset_url = wp_nonce_url(
			add_query_arg( 'action', Settings::RESET_ACTION, admin_url( 'admin-post.php' ) ),
			Settings::RESET_ACTION
		);

		$notice = isset( $_GET['mcl_notice'] ) ? sanitize_key( wp_unslash( $_GET['mcl_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->view( 'settings', compact( 'reset_url', 'notice' ) );
	}

	/**
	 * Render an admin notice partial.
	 *
	 * @param string $type    Notice type.
	 * @param string $message Message text.
	 * @return void
	 */
	public function render_notice( string $type, string $message ): void {
		$partial = MCL_PATH . 'admin/partials/notice.php';

		if ( is_readable( $partial ) ) {
			require $partial;
		}
	}

	/**
	 * Include an admin view template.
	 *
	 * @param string               $name View name.
	 * @param array<string, mixed> $vars Template variables.
	 * @return void
	 */
	private function view( string $name, array $vars = array() ): void {
		$file = MCL_PATH . 'admin/views/' . sanitize_file_name( $name ) . '.php';

		if ( ! is_readable( $file ) ) {
			return;
		}

		$admin = $this;

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $vars, EXTR_SKIP );

		require $file;
	}

	/**
	 * Build an admin page URL.
	 *
	 * @param string $slug Page slug.
	 * @return string
	 */
	private function page_url( string $slug ): string {
		return add_query_arg( 'page', $slug, admin_url( 'admin.php' ) );
	}

	/**
	 * Ensure the current user may view plugin pages.
	 *
	 * @return void
	 */
	private function guard(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'media-cleaner-lite' ) );
		}
	}
}
