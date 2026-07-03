<?php
/**
 * Settings view.
 *
 * @package MediaCleanerLite
 *
 * @var string $reset_url Nonce-protected reset URL.
 * @var string $notice    Notice key from query string.
 * @var Admin  $admin     Admin service.
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap mcl-wrap mcl-settings">
	<h1 class="mcl-title">
		<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
		<?php echo esc_html__( 'تنظیمات', 'media-cleaner-lite' ); ?>
	</h1>

	<?php
	if ( 'reset' === ( $notice ?? '' ) && isset( $admin ) && $admin instanceof Admin ) {
		$admin->render_notice( 'success', __( 'تنظیمات به مقادیر پیش‌فرض بازنشانی شدند.', 'media-cleaner-lite' ) );
	}
	?>

	<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
		<?php
		settings_fields( Settings::OPTION_GROUP );
		do_settings_sections( Settings::PAGE );
		submit_button( __( 'ذخیره تغییرات', 'media-cleaner-lite' ) );
		?>
	</form>

	<hr>

	<h2><?php echo esc_html__( 'بازنشانی', 'media-cleaner-lite' ); ?></h2>
	<p class="description"><?php echo esc_html__( 'همه تنظیمات به مقادیر پیش‌فرض بازگردانده می‌شوند. این عمل قابل بازگشت نیست.', 'media-cleaner-lite' ); ?></p>
	<p>
		<a
			href="<?php echo esc_url( $reset_url ); ?>"
			class="button button-secondary mcl-reset"
			data-mcl-confirm="<?php echo esc_attr__( 'همه تنظیمات به حالت پیش‌فرض بازنشانی شوند؟', 'media-cleaner-lite' ); ?>"
		>
			<?php echo esc_html__( 'بازنشانی تنظیمات', 'media-cleaner-lite' ); ?>
		</a>
	</p>
</div>
