<?php
/**
 * Media indexer.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indexes every attachment with metadata for later comparison.
 */
class MediaIndexer {

	/**
	 * Build index data for a single attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return array<string, mixed>
	 */
	public function index_attachment( int $attachment_id ): array {
		if ( $attachment_id <= 0 ) {
			return array();
		}

		$post = get_post( $attachment_id );

		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return array();
		}

		$mime = (string) get_post_mime_type( $attachment_id );

		if ( Helper::to_bool( Helper::get_setting( 'ignore_svg', false ) ) && str_starts_with( $mime, 'image/svg' ) ) {
			return array();
		}

		$file     = get_attached_file( $attachment_id );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$url      = wp_get_attachment_url( $attachment_id );
		$size     = ( is_string( $file ) && is_readable( $file ) ) ? (int) filesize( $file ) : 0;
		$sizes    = array();

		if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				if ( ! is_array( $size_data ) ) {
					continue;
				}

				$sizes[ (string) $size_name ] = array(
					'file'   => (string) ( $size_data['file'] ?? '' ),
					'width'  => (int) ( $size_data['width'] ?? 0 ),
					'height' => (int) ( $size_data['height'] ?? 0 ),
				);

				if ( is_string( $file ) && ! empty( $size_data['file'] ) ) {
					$size_path = path_join( dirname( $file ), (string) $size_data['file'] );
					if ( is_readable( $size_path ) ) {
						$size += (int) filesize( $size_path );
					}
				}
			}
		}

		$width  = is_array( $metadata ) ? (int) ( $metadata['width'] ?? 0 ) : 0;
		$height = is_array( $metadata ) ? (int) ( $metadata['height'] ?? 0 ) : 0;

		return array(
			'id'           => $attachment_id,
			'filename'     => is_string( $file ) ? basename( $file ) : '',
			'path'         => is_string( $file ) ? $file : '',
			'url'          => is_string( $url ) ? $url : '',
			'mime_type'    => $mime,
			'file_size'    => $size,
			'width'        => $width,
			'height'       => $height,
			'image_sizes'  => $sizes,
			'date'         => (string) $post->post_date,
			'title'        => (string) get_the_title( $attachment_id ),
			'alt'          => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Collect attachment IDs for indexing.
	 *
	 * @param int $limit Maximum attachments to index.
	 * @return array<int, int>
	 */
	public function collect_attachment_ids( int $limit = 0 ): array {
		$args = array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'posts_per_page'         => $limit > 0 ? $limit : -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
		);

		if ( Helper::to_bool( Helper::get_setting( 'ignore_svg', false ) ) ) {
			$args['post_mime_type'] = array( 'image', 'video', 'audio', 'application' );
		}

		$query = new WP_Query( $args );
		$ids   = array_map( 'absint', (array) $query->posts );

		return array_values( array_filter( $ids ) );
	}

	/**
	 * Index a batch of attachments.
	 *
	 * @param array<int, int> $attachment_ids Attachment IDs.
	 * @return array<int, array<string, mixed>>
	 */
	public function index_batch( array $attachment_ids ): array {
		$index = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			$data          = $this->index_attachment( $attachment_id );

			if ( ! empty( $data ) ) {
				$index[ $attachment_id ] = $data;
			}
		}

		return $index;
	}
}
