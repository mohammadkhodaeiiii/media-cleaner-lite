<?php
/**
 * Safe delete service.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Safely moves attachments to trash with re-checks and logging.
 */
class SafeDelete {

	/**
	 * Storage abstraction.
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * Reference detector.
	 *
	 * @var ReferenceDetector
	 */
	private ReferenceDetector $reference_detector;

	/**
	 * Constructor.
	 *
	 * @param Database          $database           Storage abstraction.
	 * @param ReferenceDetector $reference_detector Reference detector.
	 */
	public function __construct( Database $database, ReferenceDetector $reference_detector ) {
		$this->database           = $database;
		$this->reference_detector = $reference_detector;
	}

	/**
	 * Move an attachment to trash after safety checks.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $force         Skip trash setting and permanently delete.
	 * @return array<string, mixed>
	 */
	public function delete( int $attachment_id, bool $force = false ): array {
		if ( $attachment_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'پیوست نامعتبر است.', 'media-cleaner-lite' ),
			);
		}

		$post = get_post( $attachment_id );

		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'پیوست وجود ندارد.', 'media-cleaner-lite' ),
			);
		}

		if ( $this->is_referenced( $attachment_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'این فایل رسانه هنوز استفاده می‌شود و نمی‌توان آن را ایمن حذف کرد.', 'media-cleaner-lite' ),
			);
		}

		$file = get_attached_file( $attachment_id );
		if ( ! is_string( $file ) || '' === $file ) {
			return array(
				'success' => false,
				'message' => __( 'مسیر فایل پیوست قابل تأیید نبود.', 'media-cleaner-lite' ),
			);
		}

		$use_trash = Helper::to_bool( Helper::get_setting( 'trash_instead_of_delete', true ) ) && ! $force;

		if ( $use_trash ) {
			$result = wp_trash_post( $attachment_id );
		} else {
			$result = wp_delete_attachment( $attachment_id, true );
		}

		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => __( 'حذف پیوست ممکن نشد.', 'media-cleaner-lite' ),
			);
		}

		$this->database->append_delete_log(
			array(
				'attachment_id' => $attachment_id,
				'filename'      => basename( $file ),
				'action'        => $use_trash ? 'trash' : 'delete',
				'user_id'       => get_current_user_id(),
				'timestamp'     => time(),
			)
		);

		$unused = $this->database->get_unused();
		unset( $unused[ $attachment_id ] );
		$this->database->save_unused( $unused );

		return array(
			'success' => true,
			'message' => $use_trash
				? __( 'رسانه به سطل زباله منتقل شد. می‌توانید آن را از کتابخانه رسانه بازیابی کنید.', 'media-cleaner-lite' )
				: __( 'رسانه برای همیشه حذف شد.', 'media-cleaner-lite' ),
			'action'  => $use_trash ? 'trash' : 'delete',
		);
	}

	/**
	 * Restore a trashed attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed>
	 */
	public function restore( int $attachment_id ): array {
		if ( $attachment_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'پیوست نامعتبر است.', 'media-cleaner-lite' ),
			);
		}

		$post = get_post( $attachment_id );

		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'پیوست یافت نشد.', 'media-cleaner-lite' ),
			);
		}

		if ( 'trash' !== $post->post_status ) {
			return array(
				'success' => false,
				'message' => __( 'این پیوست در سطل زباله نیست.', 'media-cleaner-lite' ),
			);
		}

		$restored = wp_untrash_post( $attachment_id );

		if ( ! $restored ) {
			return array(
				'success' => false,
				'message' => __( 'بازیابی پیوست ممکن نشد.', 'media-cleaner-lite' ),
			);
		}

		$this->database->append_delete_log(
			array(
				'attachment_id' => $attachment_id,
				'filename'      => basename( (string) get_attached_file( $attachment_id ) ),
				'action'        => 'restore',
				'user_id'       => get_current_user_id(),
				'timestamp'     => time(),
			)
		);

		return array(
			'success' => true,
			'message' => __( 'رسانه از سطل زباله بازیابی شد.', 'media-cleaner-lite' ),
		);
	}

	/**
	 * Re-check whether an attachment is still referenced.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function is_referenced( int $attachment_id ): bool {
		$stored = $this->database->get_references();

		if ( isset( $stored[ $attachment_id ] ) ) {
			return true;
		}

		$post = get_post( $attachment_id );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$live = $this->reference_detector->detect_posts_batch( array( (int) $post->post_parent ) );

		return isset( $live[ $attachment_id ] );
	}
}
