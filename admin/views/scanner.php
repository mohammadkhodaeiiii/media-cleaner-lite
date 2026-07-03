<?php
/**
 * Scanner view.
 *
 * @package MediaCleanerLite
 *
 * @var bool               $has_active_scan Whether a scan is active.
 * @var bool               $is_paused       Whether scan is paused.
 * @var bool               $enabled         Scanner enabled.
 * @var array<int, string> $post_types      Selected post types.
 * @var bool               $can_scan        Whether scan can start.
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_url = add_query_arg( 'page', Settings::PAGE, admin_url( 'admin.php' ) );
?>
<div class="wrap mcl-wrap">
	<h1 class="mcl-title">
		<span class="dashicons dashicons-search" aria-hidden="true"></span>
		<?php echo esc_html__( 'اسکنر', 'media-cleaner-lite' ); ?>
	</h1>
	<p class="mcl-subtitle"><?php echo esc_html__( 'فایل‌های رسانه را ایندکس کنید و نوشته‌ها، برگه‌ها، ابزارک‌ها، منوها، تنظیمات قالب و داده صفحه‌سازها را برای ارجاعات اسکن کنید.', 'media-cleaner-lite' ); ?></p>

	<?php if ( ! $enabled ) : ?>
		<div class="notice notice-warning mcl-inline-notice">
			<p>
				<?php echo esc_html__( 'اسکنر غیرفعال است.', 'media-cleaner-lite' ); ?>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php echo esc_html__( 'در تنظیمات فعالش کنید', 'media-cleaner-lite' ); ?></a>
			</p>
		</div>
	<?php elseif ( empty( $post_types ) ) : ?>
		<div class="notice notice-warning mcl-inline-notice">
			<p>
				<?php echo esc_html__( 'هیچ نوع نوشته‌ای انتخاب نشده.', 'media-cleaner-lite' ); ?>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php echo esc_html__( 'انتخاب انواع نوشته', 'media-cleaner-lite' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<div class="mcl-card mcl-scanner" data-mcl-scanner data-mcl-can-scan="<?php echo $can_scan ? '1' : '0'; ?>" data-mcl-active="<?php echo $has_active_scan ? '1' : '0'; ?>" data-mcl-paused="<?php echo $is_paused ? '1' : '0'; ?>">
		<div class="mcl-scanner-controls">
			<button type="button" class="button button-primary button-hero" data-mcl-start <?php disabled( ! $can_scan && ! $is_paused ); ?>>
				<span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
				<?php echo $is_paused ? esc_html__( 'ادامه اسکن', 'media-cleaner-lite' ) : esc_html__( 'شروع اسکن', 'media-cleaner-lite' ); ?>
			</button>
			<button type="button" class="button button-hero" data-mcl-pause hidden>
				<span class="dashicons dashicons-controls-pause" aria-hidden="true"></span>
				<?php echo esc_html__( 'توقف', 'media-cleaner-lite' ); ?>
			</button>
			<button type="button" class="button button-hero" data-mcl-cancel hidden>
				<span class="dashicons dashicons-no" aria-hidden="true"></span>
				<?php echo esc_html__( 'لغو', 'media-cleaner-lite' ); ?>
			</button>
			<button type="button" class="button" data-mcl-clear>
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				<?php echo esc_html__( 'پاک کردن کش', 'media-cleaner-lite' ); ?>
			</button>
		</div>

		<div class="mcl-progress-wrap">
			<div class="mcl-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-mcl-progressbar>
				<span class="mcl-progress-fill" data-mcl-progress-fill style="width:0%;"></span>
			</div>
			<div class="mcl-progress-meta">
				<span class="mcl-status" data-mcl-status aria-live="polite"><?php echo esc_html__( 'آماده', 'media-cleaner-lite' ); ?></span>
				<span class="mcl-progress-text" data-mcl-progress-text>0%</span>
			</div>
		</div>

		<p class="description mcl-scan-info">
			<?php echo esc_html__( 'اسکن شامل: پیوست‌ها، محتوای نوشته، تصویر شاخص، بلوک‌های گوتنبرگ، ووکامرس، المنتور، بریکس، بیور، دیوی، ACF، ابزارک‌ها، منوها و تنظیمات قالب.', 'media-cleaner-lite' ); ?>
		</p>
	</div>
</div>
