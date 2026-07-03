<?php
/**
 * Reports view.
 *
 * @package MediaCleanerLite
 *
 * @var array<string, mixed>             $report        Last report.
 * @var string                           $dashboard_url Dashboard URL.
 * @var array<int, array<string, mixed>> $delete_log    Delete log entries.
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mcl_has_report = ! empty( $report ) && isset( $report['total_media'] );

$mcl_action_labels = array(
	'trash'   => __( 'سطل زباله', 'media-cleaner-lite' ),
	'delete'  => __( 'حذف دائمی', 'media-cleaner-lite' ),
	'restore' => __( 'بازیابی', 'media-cleaner-lite' ),
);
?>
<div class="wrap mcl-wrap">
	<h1 class="mcl-title">
		<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
		<?php echo esc_html__( 'گزارش‌ها', 'media-cleaner-lite' ); ?>
	</h1>

	<?php if ( ! $mcl_has_report ) : ?>
		<div class="mcl-card mcl-empty-state">
			<p><?php echo esc_html__( 'گزارشی در دسترس نیست. از صفحه اسکنر یک اسکن اجرا کنید.', 'media-cleaner-lite' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'page', Admin::SCANNER_SLUG, admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'باز کردن اسکنر', 'media-cleaner-lite' ); ?></a></p>
		</div>
	<?php else : ?>
		<div class="mcl-cards">
			<?php
			$mcl_stats = array(
				array( 'label' => __( 'کل فایل‌های رسانه', 'media-cleaner-lite' ), 'value' => (int) ( $report['total_media'] ?? 0 ) ),
				array( 'label' => __( 'رسانه استفاده‌شده', 'media-cleaner-lite' ), 'value' => (int) ( $report['used_media'] ?? 0 ) ),
				array( 'label' => __( 'رسانه استفاده‌نشده', 'media-cleaner-lite' ), 'value' => (int) ( $report['unused_media'] ?? 0 ) ),
				array( 'label' => __( 'پوشش ارجاع', 'media-cleaner-lite' ), 'value' => (int) ( $report['reference_coverage'] ?? 0 ) . '%', 'raw' => true ),
				array( 'label' => __( 'فضای قابل بازیابی', 'media-cleaner-lite' ), 'value' => (string) ( $report['recoverable_human'] ?? '0 B' ), 'raw' => true ),
				array( 'label' => __( 'ریسک‌های احتمالی', 'media-cleaner-lite' ), 'value' => (int) ( $report['potential_risks'] ?? 0 ) ),
				array( 'label' => __( 'مدت اسکن', 'media-cleaner-lite' ), 'value' => Helper::format_duration( (float) ( $report['duration'] ?? 0 ) ) . ' ' . __( 'ثانیه', 'media-cleaner-lite' ), 'raw' => true ),
			);
			foreach ( $mcl_stats as $mcl_stat ) :
				?>
				<div class="mcl-card">
					<span class="mcl-card-value"><?php echo esc_html( ! empty( $mcl_stat['raw'] ) ? (string) $mcl_stat['value'] : number_format_i18n( (int) $mcl_stat['value'] ) ); ?></span>
					<span class="mcl-card-label"><?php echo esc_html( $mcl_stat['label'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( ! empty( $report['media_by_mime'] ) && is_array( $report['media_by_mime'] ) ) : ?>
			<div class="mcl-card mcl-section">
				<h2 class="mcl-section-title"><?php echo esc_html__( 'رسانه بر اساس نوع MIME', 'media-cleaner-lite' ); ?></h2>
				<table class="widefat striped mcl-report-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'نوع MIME', 'media-cleaner-lite' ); ?></th>
							<th scope="col" class="mcl-num"><?php echo esc_html__( 'تعداد', 'media-cleaner-lite' ); ?></th>
							<th scope="col" class="mcl-num"><?php echo esc_html__( 'حجم', 'media-cleaner-lite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $report['media_by_mime'] as $mcl_mime => $mcl_data ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $mcl_mime ); ?></td>
								<td class="mcl-num"><?php echo esc_html( number_format_i18n( (int) ( $mcl_data['count'] ?? 0 ) ) ); ?></td>
								<td class="mcl-num"><?php echo esc_html( Helper::format_bytes( (int) ( $mcl_data['size'] ?? 0 ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $delete_log ) ) : ?>
			<div class="mcl-card mcl-section">
				<h2 class="mcl-section-title"><?php echo esc_html__( 'عملیات اخیر', 'media-cleaner-lite' ); ?></h2>
				<table class="widefat striped mcl-report-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'فایل', 'media-cleaner-lite' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'عملیات', 'media-cleaner-lite' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'تاریخ', 'media-cleaner-lite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_reverse( array_slice( $delete_log, -20 ) ) as $mcl_log ) : ?>
							<?php if ( ! is_array( $mcl_log ) ) { continue; } ?>
							<?php
							$mcl_action = (string) ( $mcl_log['action'] ?? '' );
							$mcl_action_label = $mcl_action_labels[ $mcl_action ] ?? $mcl_action;
							?>
							<tr>
								<td><?php echo esc_html( (string) ( $mcl_log['filename'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( $mcl_action_label ); ?></td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $mcl_log['timestamp'] ?? 0 ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
