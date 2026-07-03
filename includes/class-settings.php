<?php
/**
 * Settings registration and Settings API integration.
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
 * Registers plugin settings with the WordPress Settings API.
 */
final class Settings implements ServiceInterface {

	/**
	 * Settings page identifier.
	 */
	public const PAGE = 'media-cleaner-lite-settings';

	/**
	 * Settings group name.
	 */
	public const OPTION_GROUP = 'mcl_settings_group';

	/**
	 * Reset action name.
	 */
	public const RESET_ACTION = 'mcl_reset_settings';

	/**
	 * General section ID.
	 */
	private const SECTION_GENERAL = 'mcl_section_general';

	/**
	 * Performance section ID.
	 */
	private const SECTION_PERFORMANCE = 'mcl_section_performance';

	/**
	 * Safety section ID.
	 */
	private const SECTION_SAFETY = 'mcl_section_safety';

	/**
	 * Required capability.
	 */
	public const CAPABILITY = 'manage_options';

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
		$this->loader->add_action( 'admin_init', $this, 'register_settings' );
		$this->loader->add_action( 'admin_post_' . self::RESET_ACTION, $this, 'handle_reset' );
	}

	/**
	 * Register the setting, sections and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			MCL_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Helper::class, 'sanitize_settings' ),
				'default'           => Helper::default_settings(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			self::SECTION_GENERAL,
			__( 'عمومی', 'media-cleaner-lite' ),
			array( $this, 'render_general_intro' ),
			self::PAGE
		);

		$this->add_field( 'enabled', __( 'فعال‌سازی اسکنر', 'media-cleaner-lite' ), 'render_enabled_field', self::SECTION_GENERAL );
		$this->add_field( 'post_types', __( 'انواع نوشته', 'media-cleaner-lite' ), 'render_post_types_field', self::SECTION_GENERAL );
		$this->add_field( 'scan_interval', __( 'بازه اسکن', 'media-cleaner-lite' ), 'render_scan_interval_field', self::SECTION_GENERAL );
		$this->add_field( 'ignore_svg', __( 'نادیده‌گرفتن فایل‌های SVG', 'media-cleaner-lite' ), 'render_ignore_svg_field', self::SECTION_GENERAL );
		$this->add_field( 'ignore_external', __( 'نادیده‌گرفتن فایل‌های خارجی', 'media-cleaner-lite' ), 'render_ignore_external_field', self::SECTION_GENERAL );

		add_settings_section(
			self::SECTION_PERFORMANCE,
			__( 'کارایی', 'media-cleaner-lite' ),
			array( $this, 'render_performance_intro' ),
			self::PAGE
		);

		$this->add_field( 'batch_size', __( 'اندازه دسته', 'media-cleaner-lite' ), 'render_batch_size_field', self::SECTION_PERFORMANCE );
		$this->add_field( 'max_scan_time', __( 'حداکثر زمان اسکن', 'media-cleaner-lite' ), 'render_max_scan_time_field', self::SECTION_PERFORMANCE );
		$this->add_field( 'cache_lifetime', __( 'مدت اعتبار کش', 'media-cleaner-lite' ), 'render_cache_lifetime_field', self::SECTION_PERFORMANCE );

		add_settings_section(
			self::SECTION_SAFETY,
			__( 'ایمنی', 'media-cleaner-lite' ),
			array( $this, 'render_safety_intro' ),
			self::PAGE
		);

		$this->add_field( 'trash_instead_of_delete', __( 'انتقال به سطل زباله به‌جای حذف', 'media-cleaner-lite' ), 'render_trash_field', self::SECTION_SAFETY );
	}

	/**
	 * Register a single settings field.
	 *
	 * @param string $id       Field ID.
	 * @param string $label    Field label.
	 * @param string $callback Render callback.
	 * @param string $section  Section ID.
	 * @return void
	 */
	private function add_field( string $id, string $label, string $callback, string $section ): void {
		add_settings_field(
			'mcl_field_' . $id,
			$label,
			array( $this, $callback ),
			self::PAGE,
			$section,
			array( 'label_for' => 'mcl-field-' . $id )
		);
	}

	/**
	 * General section description.
	 *
	 * @return void
	 */
	public function render_general_intro(): void {
		echo '<p>' . esc_html__( 'تعیین کنید اسکنر چه چیزی را بررسی کند و کدام فایل‌ها نادیده گرفته شوند.', 'media-cleaner-lite' ) . '</p>';
	}

	/**
	 * Performance section description.
	 *
	 * @return void
	 */
	public function render_performance_intro(): void {
		echo '<p>' . esc_html__( 'پردازش دسته‌ای، محدودیت زمان اسکن و کش را برای سایت‌های بزرگ تنظیم کنید.', 'media-cleaner-lite' ) . '</p>';
	}

	/**
	 * Safety section description.
	 *
	 * @return void
	 */
	public function render_safety_intro(): void {
		echo '<p>' . esc_html__( 'نحوه حذف رسانه استفاده‌نشده را کنترل کنید. استفاده از سطل زباله برای جلوگیری از حذف تصادفی توصیه می‌شود.', 'media-cleaner-lite' ) . '</p>';
	}

	/**
	 * Render the enable checkbox.
	 *
	 * @return void
	 */
	public function render_enabled_field(): void {
		$this->checkbox( 'enabled', __( 'به اسکنر رسانه اجازه اجرا روی این سایت داده شود.', 'media-cleaner-lite' ) );
	}

	/**
	 * Render post type checkboxes.
	 *
	 * @return void
	 */
	public function render_post_types_field(): void {
		$selected = Helper::sanitize_post_types( Helper::get_setting( 'post_types', array() ) );
		$types    = Helper::scannable_post_types();

		if ( empty( $types ) ) {
			echo '<p class="description">' . esc_html__( 'هیچ نوع نوشته عمومی یافت نشد.', 'media-cleaner-lite' ) . '</p>';
			return;
		}

		echo '<fieldset>';
		foreach ( $types as $slug ) {
			$object = get_post_type_object( $slug );
			$label  = $object instanceof \WP_Post_Type ? $object->labels->singular_name : $slug;

			printf(
				'<label class="mcl-checkbox-row"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( $this->name( 'post_types' ) ),
				esc_attr( $slug ),
				checked( in_array( $slug, $selected, true ), true, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
	}

	/**
	 * Render scan interval field.
	 *
	 * @return void
	 */
	public function render_scan_interval_field(): void {
		$this->number( 'scan_interval', Helper::INTERVAL_MIN, Helper::INTERVAL_MAX, 1, __( 'ساعت (برای اسکن‌های زمان‌بندی‌شده آینده)', 'media-cleaner-lite' ) );
	}

	/**
	 * Render ignore SVG checkbox.
	 *
	 * @return void
	 */
	public function render_ignore_svg_field(): void {
		$this->checkbox( 'ignore_svg', __( 'پیوست‌های SVG از ایندکس و پاک‌سازی کنار گذاشته شوند.', 'media-cleaner-lite' ) );
	}

	/**
	 * Render ignore external checkbox.
	 *
	 * @return void
	 */
	public function render_ignore_external_field(): void {
		$this->checkbox( 'ignore_external', __( 'نشانی‌های رسانه‌ای که خارج از این سایت میزبانی می‌شوند نادیده گرفته شوند.', 'media-cleaner-lite' ) );
	}

	/**
	 * Render batch size field.
	 *
	 * @return void
	 */
	public function render_batch_size_field(): void {
		$this->number( 'batch_size', Helper::BATCH_MIN, Helper::BATCH_MAX, 1, __( 'مورد در هر دسته', 'media-cleaner-lite' ) );
	}

	/**
	 * Render maximum scan time field.
	 *
	 * @return void
	 */
	public function render_max_scan_time_field(): void {
		$this->number( 'max_scan_time', Helper::SCAN_TIME_MIN, Helper::SCAN_TIME_MAX, 30, __( 'ثانیه در هر درخواست', 'media-cleaner-lite' ) );
	}

	/**
	 * Render cache lifetime field.
	 *
	 * @return void
	 */
	public function render_cache_lifetime_field(): void {
		$this->number( 'cache_lifetime', Helper::CACHE_MIN, Helper::CACHE_MAX, 60, __( 'ثانیه', 'media-cleaner-lite' ) );
	}

	/**
	 * Render trash checkbox.
	 *
	 * @return void
	 */
	public function render_trash_field(): void {
		$this->checkbox( 'trash_instead_of_delete', __( 'رسانه استفاده‌نشده به‌جای حذف دائمی به سطل زباله وردپرس منتقل شود.', 'media-cleaner-lite' ) );
	}

	/**
	 * Handle reset to defaults.
	 *
	 * @return void
	 */
	public function handle_reset(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'شما اجازه انجام این عملیات را ندارید.', 'media-cleaner-lite' ) );
		}

		check_admin_referer( self::RESET_ACTION );

		update_option( MCL_OPTION, Helper::default_settings() );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE,
					'mcl_notice' => 'reset',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render a checkbox control.
	 *
	 * @param string $key         Setting key.
	 * @param string $description Description.
	 * @return void
	 */
	private function checkbox( string $key, string $description ): void {
		$checked = Helper::to_bool( Helper::get_setting( $key ) );

		printf(
			'<label class="mcl-checkbox-row"><input type="checkbox" id="mcl-field-%1$s" name="%2$s" value="1" %3$s> %4$s</label>',
			esc_attr( $key ),
			esc_attr( $this->name( $key ) ),
			checked( $checked, true, false ),
			esc_html( $description )
		);
	}

	/**
	 * Render a number control.
	 *
	 * @param string $key  Setting key.
	 * @param int    $min  Minimum.
	 * @param int    $max  Maximum.
	 * @param int    $step Step.
	 * @param string $unit Unit label.
	 * @return void
	 */
	private function number( string $key, int $min, int $max, int $step, string $unit ): void {
		printf(
			'<input type="number" id="mcl-field-%1$s" class="small-text" name="%2$s" value="%3$d" min="%4$d" max="%5$d" step="%6$d">',
			esc_attr( $key ),
			esc_attr( $this->name( $key ) ),
			(int) Helper::get_setting( $key ),
			$min,
			$max,
			$step
		);

		if ( '' !== $unit ) {
			echo ' <span class="mcl-unit">' . esc_html( $unit ) . '</span>';
		}
	}

	/**
	 * Build a settings field name.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function name( string $key ): string {
		return MCL_OPTION . '[' . $key . ']';
	}
}
