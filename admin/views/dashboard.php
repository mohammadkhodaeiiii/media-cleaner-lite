<?php
/**
 * Dashboard view.
 *
 * @package MediaCleanerLite
 *
 * @var array<string, mixed> $report          Last stored report.
 * @var bool                 $has_active_scan Whether a scan is in progress.
 * @var bool                 $is_paused       Whether scan is paused.
 * @var bool                 $enabled         Whether scanning is enabled.
 * @var string               $scanner_url     Scanner page URL.
 * @var string               $unused_url      Unused media URL.
 * @var string               $reports_url     Reports page URL.
 * @var string               $settings_url    Settings page URL.
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mcl_has_report = ! empty( $report ) && isset( $report['total_media'] );
$mcl_coverage   = (int) ( $report['reference_coverage'] ?? 0 );
?>
<div class="wrap mcl-wrap">
	<h1 class="mcl-title">
		<span class="dashicons dashicons-images-alt2" aria-hidden="true"></span>
		<?php echo esc_html__( 'پاک‌سازی رسانه', 'media-cleaner-lite' ); ?>
	</h1>
	<p class="mcl-subtitle"><?php echo esc_html__( 'رسانه‌های استفاده‌نشده را شناسایی کنید، ارجاعات را در سایت تحلیل کنید و فضای ذخیره‌سازی را ایمن بازیابی کنید.', 'media-cleaner-lite' ); ?></p>

	<?php if ( ! $enabled ) : ?>
		<div class="notice notice-warning mcl-inline-notice">
			<p>
				<?php echo esc_html__( 'اسکنر در حال حاضر غیرفعال است.', 'media-cleaner-lite' ); ?>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php echo esc_html__( 'در تنظیمات فعالش کنید', 'media-cleaner-lite' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $mcl_has_report ) : ?>
		<div class="mcl-score-row">
			<div class="mcl-score" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: percentage. */ __( 'پوشش ارجاع: %d درصد', 'media-cleaner-lite' ), $mcl_coverage ) ); ?>" style="--mcl-score: <?php echo (int) $mcl_coverage; ?>;">
				<span class="mcl-score-value"><?php echo esc_html( number_format_i18n( $mcl_coverage ) ); ?>%</span>
				<span class="mcl-score-label"><?php echo esc_html__( 'پوشش ارجاع', 'media-cleaner-lite' ); ?></span>
			</div>
			<div class="mcl-score-meta">
				<?php
				$mcl_completed = (int) ( $report['completed_at'] ?? 0 );
				if ( $mcl_completed > 0 ) :
					?>
					<p>
						<strong><?php echo esc_html__( 'آخرین اسکن:', 'media-cleaner-lite' ); ?></strong>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $mcl_completed ) ); ?>
					</p>
				<?php endif; ?>
				<p>
					<strong><?php echo esc_html__( 'فضای قابل بازیابی:', 'media-cleaner-lite' ); ?></strong>
					<?php echo esc_html( (string) ( $report['recoverable_human'] ?? '0 B' ) ); ?>
				</p>
				<p class="mcl-actions">
					<a class="button button-primary" href="<?php echo esc_url( $scanner_url ); ?>"><?php echo esc_html__( 'اجرای اسکنر', 'media-cleaner-lite' ); ?></a>
					<a class="button" href="<?php echo esc_url( $unused_url ); ?>"><?php echo esc_html__( 'رسانه استفاده‌نشده', 'media-cleaner-lite' ); ?></a>
					<a class="button" href="<?php echo esc_url( $reports_url ); ?>"><?php echo esc_html__( 'گزارش کامل', 'media-cleaner-lite' ); ?></a>
				</p>
			</div>
		</div>

		<div class="mcl-cards">
			<?php
			$mcl_cards = array(
				array(
					'label' => __( 'کل رسانه‌ها', 'media-cleaner-lite' ),
					'value' => (int) ( $report['total_media'] ?? 0 ),
					'icon'  => 'dashicons-format-gallery',
					'tone'  => 'neutral',
				),
				array(
					'label' => __( 'رسانه استفاده‌شده', 'media-cleaner-lite' ),
					'value' => (int) ( $report['used_media'] ?? 0 ),
					'icon'  => 'dashicons-yes-alt',
					'tone'  => 'good',
				),
				array(
					'label' => __( 'رسانه استفاده‌نشده', 'media-cleaner-lite' ),
					'value' => (int) ( $report['unused_media'] ?? 0 ),
					'icon'  => 'dashicons-warning',
					'tone'  => 'warn',
				),
				array(
					'label' => __( 'احتمالاً استفاده‌نشده', 'media-cleaner-lite' ),
					'value' => (int) ( $report['potentially_unused'] ?? 0 ),
					'icon'  => 'dashicons-info',
					'tone'  => 'info',
				),
				array(
					'label' => __( 'قابل بازیابی', 'media-cleaner-lite' ),
					'value' => (string) ( $report['recoverable_human'] ?? '0 B' ),
					'icon'  => 'dashicons-database',
					'tone'  => 'good',
					'raw'   => true,
				),
				array(
					'label' => __( 'مدت اسکن', 'media-cleaner-lite' ),
					'value' => sprintf(
						/* translators: %s: seconds */
						__( '%s ثانیه', 'media-cleaner-lite' ),
						Helper::format_duration( (float) ( $report['duration'] ?? 0 ) )
					),
					'icon'  => 'dashicons-clock',
					'tone'  => 'neutral',
					'raw'   => true,
				),
			);

			foreach ( $mcl_cards as $mcl_card ) :
				?>
				<div class="mcl-card mcl-card--<?php echo esc_attr( $mcl_card['tone'] ); ?>">
					<span class="dashicons <?php echo esc_attr( $mcl_card['icon'] ); ?>" aria-hidden="true"></span>
					<span class="mcl-card-value">
						<?php
						if ( ! empty( $mcl_card['raw'] ) ) {
							echo esc_html( (string) $mcl_card['value'] );
						} else {
							echo esc_html( number_format_i18n( (int) $mcl_card['value'] ) );
						}
						?>
					</span>
					<span class="mcl-card-label"><?php echo esc_html( $mcl_card['label'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( ! empty( $report['largest_unused'] ) && is_array( $report['largest_unused'] ) ) : ?>
			<div class="mcl-card mcl-section">
				<h2 class="mcl-section-title"><?php echo esc_html__( 'بزرگ‌ترین فایل‌های استفاده‌نشده', 'media-cleaner-lite' ); ?></h2>
				<table class="widefat striped mcl-report-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'فایل', 'media-cleaner-lite' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'نوع', 'media-cleaner-lite' ); ?></th>
							<th scope="col" class="mcl-num"><?php echo esc_html__( 'حجم', 'media-cleaner-lite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $report['largest_unused'], 0, 5 ) as $mcl_file ) : ?>
							<?php if ( ! is_array( $mcl_file ) ) { continue; } ?>
							<tr>
								<td><?php echo esc_html( (string) ( $mcl_file['filename'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $mcl_file['mime_type'] ?? '' ) ); ?></td>
								<td class="mcl-num"><?php echo esc_html( Helper::format_bytes( (int) ( $mcl_file['file_size'] ?? 0 ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="mcl-card mcl-empty-state">
			<span class="dashicons dashicons-images-alt2" aria-hidden="true"></span>
			<h2><?php echo esc_html__( 'هنوز اسکنی اجرا نشده', 'media-cleaner-lite' ); ?></h2>
			<p><?php echo esc_html__( 'اولین اسکن را اجرا کنید تا فایل‌های رسانه استفاده‌نشده را پیدا کرده و فضای دیسک را بازیابی کنید.', 'media-cleaner-lite' ); ?></p>
			<p><a class="button button-primary button-hero" href="<?php echo esc_url( $scanner_url ); ?>"><?php echo esc_html__( 'شروع اسکن', 'media-cleaner-lite' ); ?></a></p>
		</div>
	<?php endif; ?>
</div>
