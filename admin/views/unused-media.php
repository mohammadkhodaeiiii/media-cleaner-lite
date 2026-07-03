<?php
/**
 * Unused media view.
 *
 * @package MediaCleanerLite
 *
 * @var array<int, array<string, mixed>> $unused Unused media items.
 * @var string                           $status Status filter.
 * @var string                           $search Search query.
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_url = add_query_arg( 'page', Admin::UNUSED_SLUG, admin_url( 'admin.php' ) );

$mcl_status_labels = array(
	'unused'             => __( 'استفاده‌نشده', 'media-cleaner-lite' ),
	'potentially_unused' => __( 'احتمالاً استفاده‌نشده', 'media-cleaner-lite' ),
	'referenced'         => __( 'استفاده‌شده', 'media-cleaner-lite' ),
);
?>
<div class="wrap mcl-wrap">
	<h1 class="mcl-title">
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<?php echo esc_html__( 'رسانه استفاده‌نشده', 'media-cleaner-lite' ); ?>
	</h1>
	<p class="mcl-subtitle"><?php echo esc_html__( 'رسانه‌های استفاده‌نشده و احتمالاً استفاده‌نشده را قبل از انتقال به سطل زباله بررسی کنید.', 'media-cleaner-lite' ); ?></p>

	<form class="mcl-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( Admin::UNUSED_SLUG ); ?>">
		<label for="mcl-status-filter" class="screen-reader-text"><?php echo esc_html__( 'فیلتر بر اساس وضعیت', 'media-cleaner-lite' ); ?></label>
		<select id="mcl-status-filter" name="mcl_status">
			<option value="unused" <?php selected( $status, 'unused' ); ?>><?php echo esc_html__( 'استفاده‌نشده', 'media-cleaner-lite' ); ?></option>
			<option value="potentially_unused" <?php selected( $status, 'potentially_unused' ); ?>><?php echo esc_html__( 'احتمالاً استفاده‌نشده', 'media-cleaner-lite' ); ?></option>
			<option value="referenced" <?php selected( $status, 'referenced' ); ?>><?php echo esc_html__( 'استفاده‌شده', 'media-cleaner-lite' ); ?></option>
			<option value="all" <?php selected( $status, 'all' ); ?>><?php echo esc_html__( 'همه', 'media-cleaner-lite' ); ?></option>
		</select>
		<label for="mcl-search" class="screen-reader-text"><?php echo esc_html__( 'جستجو', 'media-cleaner-lite' ); ?></label>
		<input type="search" id="mcl-search" name="mcl_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'جستجوی فایل‌ها…', 'media-cleaner-lite' ); ?>">
		<button type="submit" class="button"><?php echo esc_html__( 'فیلتر', 'media-cleaner-lite' ); ?></button>
	</form>

	<?php if ( empty( $unused ) ) : ?>
		<div class="mcl-card mcl-empty-state">
			<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
			<h2><?php echo esc_html__( 'رسانه‌ای یافت نشد', 'media-cleaner-lite' ); ?></h2>
			<p><?php echo esc_html__( 'ابتدا یک اسکن اجرا کنید یا فیلترها را تغییر دهید.', 'media-cleaner-lite' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped mcl-unused-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'پیش‌نمایش', 'media-cleaner-lite' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'فایل', 'media-cleaner-lite' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'نوع', 'media-cleaner-lite' ); ?></th>
					<th scope="col" class="mcl-num"><?php echo esc_html__( 'حجم', 'media-cleaner-lite' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'وضعیت', 'media-cleaner-lite' ); ?></th>
					<th scope="col" class="mcl-num"><?php echo esc_html__( 'اطمینان', 'media-cleaner-lite' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'عملیات', 'media-cleaner-lite' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $unused as $mcl_item ) : ?>
					<?php
					if ( ! is_array( $mcl_item ) ) {
						continue;
					}
					$mcl_id     = (int) ( $mcl_item['id'] ?? 0 );
					$mcl_status = (string) ( $mcl_item['status'] ?? '' );
					$mcl_thumb  = wp_get_attachment_image( $mcl_id, array( 48, 48 ), true );
					$mcl_label  = $mcl_status_labels[ $mcl_status ] ?? $mcl_status;
					?>
					<tr data-mcl-media-row data-attachment-id="<?php echo esc_attr( (string) $mcl_id ); ?>">
						<td><?php echo $mcl_thumb ? wp_kses_post( $mcl_thumb ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td>
							<strong><?php echo esc_html( (string) ( $mcl_item['filename'] ?? '' ) ); ?></strong>
							<?php if ( ! empty( $mcl_item['title'] ) ) : ?>
								<br><span class="description"><?php echo esc_html( (string) $mcl_item['title'] ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) ( $mcl_item['mime_type'] ?? '' ) ); ?></td>
						<td class="mcl-num"><?php echo esc_html( Helper::format_bytes( (int) ( $mcl_item['file_size'] ?? 0 ) ) ); ?></td>
						<td>
							<span class="mcl-badge mcl-badge--<?php echo esc_attr( 'referenced' === $mcl_status ? 'high' : ( 'unused' === $mcl_status ? 'warn' : 'medium' ) ); ?>">
								<?php echo esc_html( $mcl_label ); ?>
							</span>
						</td>
						<td class="mcl-num"><?php echo esc_html( number_format_i18n( (int) ( $mcl_item['confidence'] ?? 0 ) ) ); ?>%</td>
						<td class="mcl-actions-cell">
							<?php if ( 'referenced' !== $mcl_status ) : ?>
								<button type="button" class="button button-small" data-mcl-delete data-attachment-id="<?php echo esc_attr( (string) $mcl_id ); ?>">
									<?php echo esc_html__( 'انتقال به سطل زباله', 'media-cleaner-lite' ); ?>
								</button>
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
