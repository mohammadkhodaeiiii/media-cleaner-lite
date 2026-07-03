<?php
/**
 * Admin asset registration and enqueueing.
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
 * Registers and enqueues admin assets on plugin screens only.
 */
final class Assets implements ServiceInterface {

	/**
	 * Admin handle prefix.
	 */
	private const HANDLE = 'media-cleaner-lite';

	/**
	 * Shared hook loader.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Shared hook loader.
	 */
	public function __construct( Loader $loader ) {
		$this->loader = $loader;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin', 10, 1 );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin( string $hook_suffix ): void {
		if ( ! $this->is_plugin_screen( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE . '-admin',
			MCL_URL . 'assets/css/admin.css',
			array(),
			$this->asset_version( 'assets/css/admin.css' )
		);

		wp_enqueue_script(
			self::HANDLE . '-admin',
			MCL_URL . 'assets/js/admin.js',
			array(),
			$this->asset_version( 'assets/js/admin.js' ),
			array( 'in_footer' => true )
		);

		wp_enqueue_script(
			self::HANDLE . '-scanner',
			MCL_URL . 'assets/js/scanner.js',
			array(),
			$this->asset_version( 'assets/js/scanner.js' ),
			array( 'in_footer' => true )
		);

		wp_localize_script( self::HANDLE . '-scanner', 'mclScan', $this->script_data() );
	}

	/**
	 * Build scanner script data.
	 *
	 * @return array<string, mixed>
	 */
	private function script_data(): array {
		return array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( Ajax::NONCE_ACTION ),
			'actions' => array(
				'start'    => Ajax::ACTION_START,
				'continue' => Ajax::ACTION_CONTINUE,
				'pause'    => Ajax::ACTION_PAUSE,
				'cancel'   => Ajax::ACTION_CANCEL,
				'clear'    => Ajax::ACTION_CLEAR,
				'refresh'  => Ajax::ACTION_REFRESH,
				'delete'   => Ajax::ACTION_DELETE,
				'restore'  => Ajax::ACTION_RESTORE,
			),
			'i18n'    => array(
				'starting'   => __( 'در حال شروع اسکن…', 'media-cleaner-lite' ),
				'indexing'   => __( 'در حال ایندکس رسانه…', 'media-cleaner-lite' ),
				'scanning'   => __( 'در حال اسکن ارجاعات…', 'media-cleaner-lite' ),
				'analyzing'  => __( 'در حال تحلیل نتایج…', 'media-cleaner-lite' ),
				'complete'   => __( 'اسکن کامل شد.', 'media-cleaner-lite' ),
				'paused'     => __( 'اسکن متوقف شد.', 'media-cleaner-lite' ),
				'cancelled'  => __( 'اسکن لغو شد.', 'media-cleaner-lite' ),
				'error'      => __( 'خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'media-cleaner-lite' ),
				'confirm'    => __( 'آیا مطمئن هستید؟', 'media-cleaner-lite' ),
				'cleaveWarn' => __( 'یک اسکن در حال اجراست. خروج از صفحه آن را متوقف می‌کند.', 'media-cleaner-lite' ),
				'cacheClear' => __( 'کش پاک شد.', 'media-cleaner-lite' ),
				'deleted'    => __( 'رسانه به سطل زباله منتقل شد.', 'media-cleaner-lite' ),
				'restored'   => __( 'رسانه بازیابی شد.', 'media-cleaner-lite' ),
				'deleteFail' => __( 'حذف رسانه ممکن نشد.', 'media-cleaner-lite' ),
			),
		);
	}

	/**
	 * Whether the current screen belongs to this plugin.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return bool
	 */
	private function is_plugin_screen( string $hook_suffix ): bool {
		return false !== strpos( $hook_suffix, 'media-cleaner-lite' );
	}

	/**
	 * Resolve asset version.
	 *
	 * @param string $relative_path Path relative to plugin root.
	 * @return string
	 */
	private function asset_version( string $relative_path ): string {
		$absolute = MCL_PATH . ltrim( $relative_path, '/' );

		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) && is_readable( $absolute ) ) {
			$mtime = filemtime( $absolute );

			if ( false !== $mtime ) {
				return (string) $mtime;
			}
		}

		return MCL_VERSION;
	}
}
