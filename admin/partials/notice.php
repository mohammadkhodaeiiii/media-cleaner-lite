<?php
/**
 * Admin notice partial.
 *
 * @package MediaCleanerLite
 *
 * @var string $type    Notice type.
 * @var string $message Message text.
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mcl_allowed_types = array( 'success', 'error', 'warning', 'info' );
$mcl_type          = in_array( $type ?? '', $mcl_allowed_types, true ) ? $type : 'info';
$mcl_message       = isset( $message ) ? (string) $message : '';

if ( '' === $mcl_message ) {
	return;
}
?>
<div class="notice notice-<?php echo esc_attr( $mcl_type ); ?> is-dismissible">
	<p><?php echo esc_html( $mcl_message ); ?></p>
</div>
