<?php
/**
 * Reference detector registry and orchestrator.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

use MediaCleanerLite\Detectors\DetectorInterface;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modular reference detection across WordPress content, builders, and metadata.
 */
class ReferenceDetector {

	/**
	 * Registered detectors.
	 *
	 * @var array<int, DetectorInterface>
	 */
	private array $detectors = array();

	/**
	 * Constructor — register built-in detectors.
	 */
	public function __construct() {
		$this->register_detectors();
	}

	/**
	 * Run all detectors and merge results.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function detect_all(): array {
		$references = array();

		foreach ( $this->detectors as $detector ) {
			foreach ( $detector->detect() as $ref ) {
				$attachment_id = (int) ( $ref['attachment_id'] ?? 0 );

				if ( $attachment_id <= 0 ) {
					continue;
				}

				if ( ! isset( $references[ $attachment_id ] ) ) {
					$references[ $attachment_id ] = array(
						'attachment_id' => $attachment_id,
						'sources'       => array(),
						'confidence'    => 0,
					);
				}

				$references[ $attachment_id ]['sources'][] = array(
					'detector'   => $detector->id(),
					'label'      => $detector->label(),
					'source'     => (string) ( $ref['source'] ?? '' ),
					'confidence' => (int) ( $ref['confidence'] ?? 80 ),
				);

				$references[ $attachment_id ]['confidence'] = max(
					(int) $references[ $attachment_id ]['confidence'],
					(int) ( $ref['confidence'] ?? 80 )
				);
			}
		}

		/**
		 * Filter detected references before they are stored.
		 *
		 * @param array<int, array<string, mixed>> $references Reference map.
		 */
		return (array) apply_filters( 'mcl_detected_references', $references );
	}

	/**
	 * Detect references inside a batch of posts.
	 *
	 * @param array<int, int> $post_ids Post IDs.
	 * @return array<int, array<string, mixed>>
	 */
	public function detect_posts_batch( array $post_ids ): array {
		$references = array();

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			$post    = get_post( $post_id );

			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$found = array_merge(
				$this->detect_post_content( $post ),
				$this->detect_featured_image( $post ),
				$this->detect_gutenberg_blocks( $post ),
				$this->detect_classic_editor( $post ),
				$this->detect_woocommerce( $post ),
				$this->detect_elementor( $post ),
				$this->detect_bricks( $post ),
				$this->detect_beaver_builder( $post ),
				$this->detect_divi( $post ),
				$this->detect_acf_fields( $post ),
				$this->detect_custom_fields( $post ),
				$this->detect_shortcodes( $post )
			);

			foreach ( $found as $ref ) {
				$attachment_id = (int) ( $ref['attachment_id'] ?? 0 );

				if ( $attachment_id <= 0 ) {
					continue;
				}

				if ( ! isset( $references[ $attachment_id ] ) ) {
					$references[ $attachment_id ] = array(
						'attachment_id' => $attachment_id,
						'sources'       => array(),
						'confidence'    => 0,
					);
				}

				$references[ $attachment_id ]['sources'][] = $ref;
				$references[ $attachment_id ]['confidence'] = max(
					(int) $references[ $attachment_id ]['confidence'],
					(int) ( $ref['confidence'] ?? 80 )
				);
			}
		}

		return $references;
	}

	/**
	 * Detect references in widgets, menus, theme mods and attachment metadata.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function detect_global(): array {
		$references = array();

		foreach ( array_merge(
			$this->detect_widgets(),
			$this->detect_menus(),
			$this->detect_theme_mods(),
			$this->detect_attachment_metadata()
		) as $ref ) {
			$attachment_id = (int) ( $ref['attachment_id'] ?? 0 );

			if ( $attachment_id <= 0 ) {
				continue;
			}

			if ( ! isset( $references[ $attachment_id ] ) ) {
				$references[ $attachment_id ] = array(
					'attachment_id' => $attachment_id,
					'sources'       => array(),
					'confidence'    => 0,
				);
			}

			$references[ $attachment_id ]['sources'][] = $ref;
			$references[ $attachment_id ]['confidence'] = max(
				(int) $references[ $attachment_id ]['confidence'],
				(int) ( $ref['confidence'] ?? 80 )
			);
		}

		return $references;
	}

	/**
	 * Collect post IDs for reference scanning.
	 *
	 * @param array<int, string> $post_types Post types.
	 * @param int                $limit      Maximum posts.
	 * @return array<int, int>
	 */
	public function collect_post_ids( array $post_types, int $limit ): array {
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}

		$query = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit > 0 ? $limit : -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
			)
		);

		$ids = array_map( 'absint', (array) $query->posts );

		return array_values( array_filter( $ids ) );
	}

	/**
	 * Register built-in modular detectors.
	 *
	 * @return void
	 */
	private function register_detectors(): void {
		$this->detectors = array(
			new Detectors\GlobalReferenceDetector( $this ),
		);

		/**
		 * Filter the list of reference detectors.
		 *
		 * @param array<int, DetectorInterface> $detectors Registered detectors.
		 */
		$this->detectors = (array) apply_filters( 'mcl_reference_detectors', $this->detectors );
	}

	/**
	 * Detect references in post content.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_post_content( \WP_Post $post ): array {
		return $this->map_ids_to_refs(
			Helper::extract_attachment_ids_from_content( (string) $post->post_content ),
			'post_content',
			__( 'محتوای نوشته', 'media-cleaner-lite' ),
			95,
			'post:' . (int) $post->ID
		);
	}

	/**
	 * Detect featured image.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_featured_image( \WP_Post $post ): array {
		$thumbnail_id = (int) get_post_thumbnail_id( $post );

		if ( $thumbnail_id <= 0 ) {
			return array();
		}

		return $this->map_ids_to_refs(
			array( $thumbnail_id ),
			'featured_image',
			__( 'تصویر شاخص', 'media-cleaner-lite' ),
			100,
			'post:' . (int) $post->ID
		);
	}

	/**
	 * Detect Gutenberg block references.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_gutenberg_blocks( \WP_Post $post ): array {
		if ( ! function_exists( 'parse_blocks' ) || ! has_blocks( $post ) ) {
			return array();
		}

		$ids    = array();
		$blocks = parse_blocks( (string) $post->post_content );
		$this->walk_blocks( $blocks, $ids );

		return $this->map_ids_to_refs(
			$ids,
			'gutenberg',
			__( 'بلوک‌های گوتنبرگ', 'media-cleaner-lite' ),
			98,
			'post:' . (int) $post->ID
		);
	}

	/**
	 * Walk parsed blocks recursively.
	 *
	 * @param array<int, array<string, mixed>> $blocks Block tree.
	 * @param array<int, int>                  $ids    Collected IDs.
	 * @return void
	 */
	private function walk_blocks( array $blocks, array &$ids ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( ! empty( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				if ( ! empty( $block['attrs']['id'] ) ) {
					$ids[] = absint( $block['attrs']['id'] );
				}
				if ( ! empty( $block['attrs']['mediaId'] ) ) {
					$ids[] = absint( $block['attrs']['mediaId'] );
				}
				if ( ! empty( $block['attrs']['ids'] ) && is_array( $block['attrs']['ids'] ) ) {
					foreach ( $block['attrs']['ids'] as $id ) {
						$ids[] = absint( $id );
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$this->walk_blocks( $block['innerBlocks'], $ids );
			}

			if ( ! empty( $block['innerHTML'] ) ) {
				$ids = array_merge( $ids, Helper::extract_attachment_ids_from_content( (string) $block['innerHTML'] ) );
			}
		}
	}

	/**
	 * Detect classic editor embedded media.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_classic_editor( \WP_Post $post ): array {
		$content = (string) $post->post_content;
		$ids     = array();

		if ( preg_match_all( '/\[gallery[^\]]*ids=["\']([^"\']+)["\']/', $content, $matches ) ) {
			foreach ( $matches[1] as $group ) {
				foreach ( preg_split( '/\s*,\s*/', (string) $group ) ?: array() as $id ) {
					$ids[] = absint( $id );
				}
			}
		}

		if ( preg_match_all( '/\[caption[^\]]*id=["\']attachment_(\d+)["\']/', $content, $caption_matches ) ) {
			foreach ( $caption_matches[1] as $id ) {
				$ids[] = absint( $id );
			}
		}

		return $this->map_ids_to_refs(
			$ids,
			'classic_editor',
			__( 'ویرایشگر کلاسیک', 'media-cleaner-lite' ),
			95,
			'post:' . (int) $post->ID
		);
	}

	/**
	 * Detect WooCommerce product images.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_woocommerce( \WP_Post $post ): array {
		if ( 'product' !== $post->post_type ) {
			return array();
		}

		$ids = array();

		$thumb = (int) get_post_meta( (int) $post->ID, '_thumbnail_id', true );
		if ( $thumb > 0 ) {
			$ids[] = $thumb;
		}

		$gallery = get_post_meta( (int) $post->ID, '_product_image_gallery', true );
		if ( is_string( $gallery ) && '' !== $gallery ) {
			foreach ( explode( ',', $gallery ) as $id ) {
				$ids[] = absint( $id );
			}
		}

		return $this->map_ids_to_refs(
			$ids,
			'woocommerce',
			__( 'ووکامرس', 'media-cleaner-lite' ),
			100,
			'product:' . (int) $post->ID
		);
	}

	/**
	 * Detect Elementor data references.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_elementor( \WP_Post $post ): array {
		$data = get_post_meta( (int) $post->ID, '_elementor_data', true );

		if ( ! is_string( $data ) || '' === $data ) {
			return array();
		}

		return $this->map_ids_to_refs(
			Helper::extract_attachment_ids_from_content( $data ),
			'elementor',
			__( 'المنتور', 'media-cleaner-lite' ),
			98,
			'post:' . (int) $post->ID
		);
	}

	/**
	 * Detect Bricks Builder data references.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_bricks( \WP_Post $post ): array {
		$data = get_post_meta( (int) $post->ID, '_bricks_page_content_2', true );

		if ( empty( $data ) ) {
			$data = get_post_meta( (int) $post->ID, '_bricks_page_content', true );
		}

		if ( ! is_string( $data ) || '' === $data ) {
			return array();
		}

		return $this->map_ids_to_refs(
			Helper::extract_attachment_ids_from_content( $data ),
			'bricks',
			__( 'بریکس بیلدر', 'media-cleaner-lite' ),
			98,
			'post:' . (int) $post->ID
		);
	}

	/**
	 * Detect Beaver Builder data references.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_beaver_builder( \WP_Post $post ): array {
		$data = get_post_meta( (int) $post->ID, '_fl_builder_data', true );

		if ( empty( $data ) ) {
			return array();
		}

		$serialized = is_array( $data ) ? wp_json_encode( $data ) : (string) $data;

		return $this->map_ids_to_refs(
			Helper::extract_attachment_ids_from_content( $serialized ),
			'beaver_builder',
			__( 'بیور بیلدر', 'media-cleaner-lite' ),
			98,
			'post:' . (int) $post->ID
		);
	}

	/**
	 * Detect Divi Builder data references.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_divi( \WP_Post $post ): array {
		$content = (string) get_post_meta( (int) $post->ID, '_et_pb_use_builder', true );

		if ( '' === $content ) {
			return array();
		}

		return $this->map_ids_to_refs(
			Helper::extract_attachment_ids_from_content( (string) $post->post_content ),
			'divi',
			__( 'دیوی بیلدر', 'media-cleaner-lite' ),
			95,
			'post:' . (int) $post->ID
		);
	}

	/**
	 * Detect ACF image field references.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_acf_fields( \WP_Post $post ): array {
		$ids = array();

		if ( ! function_exists( 'get_fields' ) ) {
			return array();
		}

		$fields = get_fields( (int) $post->ID );

		if ( ! is_array( $fields ) ) {
			return array();
		}

		$this->walk_field_values( $fields, $ids );

		return $this->map_ids_to_refs(
			$ids,
			'acf',
			__( 'فیلدهای ACF', 'media-cleaner-lite' ),
			97,
			'post:' . (int) $post->ID
		);
	}

	/**
	 * Detect generic custom field references.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_custom_fields( \WP_Post $post ): array {
		$ids    = array();
		$meta   = get_post_meta( (int) $post->ID );
		$skip   = array( '_edit_lock', '_edit_last', '_wp_page_template', '_thumbnail_id', '_elementor_data', '_fl_builder_data' );

		foreach ( $meta as $key => $values ) {
			if ( in_array( (string) $key, $skip, true ) || '_' !== (string) $key[0] ) {
				continue;
			}

			foreach ( (array) $values as $value ) {
				if ( is_numeric( $value ) && 'attachment' === get_post_type( (int) $value ) ) {
					$ids[] = (int) $value;
					continue;
				}

				if ( is_string( $value ) ) {
					$ids = array_merge( $ids, Helper::extract_attachment_ids_from_content( $value ) );
				}
			}
		}

		return $this->map_ids_to_refs(
			$ids,
			'custom_fields',
			__( 'فیلدهای سفارشی', 'media-cleaner-lite' ),
			85,
			'post:' . (int) $post->ID
		);
	}

	/**
	 * Detect shortcode references.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_shortcodes( \WP_Post $post ): array {
		$content = (string) $post->post_content;
		$ids     = array();

		if ( preg_match_all( '/\[(?:image|video|audio|embed_media)[^\]]*id=["\']?(\d+)["\']?/', $content, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				$ids[] = absint( $id );
			}
		}

		return $this->map_ids_to_refs(
			$ids,
			'shortcodes',
			__( 'کدهای کوتاه', 'media-cleaner-lite' ),
			90,
			'post:' . (int) $post->ID
		);
	}

	/**
	 * Detect widget references.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_widgets(): array {
		$ids      = array();
		$sidebars = wp_get_sidebars_widgets();

		if ( ! is_array( $sidebars ) ) {
			return array();
		}

		foreach ( $sidebars as $sidebar => $widgets ) {
			if ( ! is_array( $widgets ) ) {
				continue;
			}

			foreach ( $widgets as $widget_id ) {
				if ( ! is_string( $widget_id ) ) {
					continue;
				}

				$option = get_option( 'widget_' . preg_replace( '/-\d+$/', '', $widget_id ) );
				if ( is_array( $option ) ) {
					$this->walk_field_values( $option, $ids );
				}
			}
		}

		return $this->map_ids_to_refs(
			$ids,
			'widgets',
			__( 'ابزارک‌ها', 'media-cleaner-lite' ),
			90,
			'widgets'
		);
	}

	/**
	 * Detect menu item references.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_menus(): array {
		$ids   = array();
		$menus = wp_get_nav_menus();

		if ( ! is_array( $menus ) ) {
			return array();
		}

		foreach ( $menus as $menu ) {
			if ( ! $menu instanceof \WP_Term ) {
				continue;
			}

			$items = wp_get_nav_menu_items( (int) $menu->term_id );

			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				if ( ! $item instanceof \WP_Post ) {
					continue;
				}

				$object_id = (int) $item->object_id;
				if ( 'attachment' === $item->object && $object_id > 0 ) {
					$ids[] = $object_id;
				}

				$thumb = (int) get_post_meta( (int) $item->ID, '_menu_item_image', true );
				if ( $thumb > 0 ) {
					$ids[] = $thumb;
				}
			}
		}

		return $this->map_ids_to_refs(
			$ids,
			'menus',
			__( 'منوها', 'media-cleaner-lite' ),
			92,
			'menus'
		);
	}

	/**
	 * Detect theme mod / customizer references.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_theme_mods(): array {
		$mods = get_theme_mods();
		$ids  = array();

		if ( is_array( $mods ) ) {
			$this->walk_field_values( $mods, $ids );
		}

		return $this->map_ids_to_refs(
			$ids,
			'theme_mods',
			__( 'تنظیمات قالب / سفارشی‌ساز', 'media-cleaner-lite' ),
			88,
			'customizer'
		);
	}

	/**
	 * Detect parent/child attachment relationships in metadata.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function detect_attachment_metadata(): array {
		$refs = array();

		$query = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => 100,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'post_parent__not_in'    => array( 0 ),
			)
		);

		foreach ( (array) $query->posts as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			$parent        = (int) wp_get_post_parent_id( $attachment_id );

			if ( $parent > 0 ) {
				$refs[] = array(
					'attachment_id' => $attachment_id,
					'detector'      => 'attachment_metadata',
					'label'         => __( 'متادیتای پیوست', 'media-cleaner-lite' ),
					'source'        => 'parent:' . $parent,
					'confidence'    => 70,
				);
			}
		}

		return $refs;
	}

	/**
	 * Recursively walk field values for attachment IDs.
	 *
	 * @param mixed             $value Field value.
	 * @param array<int, int>   $ids   Collected IDs.
	 * @return void
	 */
	private function walk_field_values( mixed $value, array &$ids ): void {
		if ( is_numeric( $value ) ) {
			$id = (int) $value;
			if ( $id > 0 && 'attachment' === get_post_type( $id ) ) {
				$ids[] = $id;
			}
			return;
		}

		if ( is_string( $value ) ) {
			$ids = array_merge( $ids, Helper::extract_attachment_ids_from_content( $value ) );
			if ( is_numeric( $value ) ) {
				$id = (int) $value;
				if ( $id > 0 && 'attachment' === get_post_type( $id ) ) {
					$ids[] = $id;
				}
			}
			return;
		}

		if ( is_array( $value ) ) {
			if ( isset( $value['ID'] ) && is_numeric( $value['ID'] ) ) {
				$ids[] = (int) $value['ID'];
			}
			foreach ( $value as $item ) {
				$this->walk_field_values( $item, $ids );
			}
		}
	}

	/**
	 * Map attachment IDs to reference records.
	 *
	 * @param array<int, int> $ids         Attachment IDs.
	 * @param string          $detector    Detector ID.
	 * @param string          $label       Detector label.
	 * @param int             $confidence  Confidence score.
	 * @param string          $source      Source identifier.
	 * @return array<int, array<string, mixed>>
	 */
	private function map_ids_to_refs( array $ids, string $detector, string $label, int $confidence, string $source ): array {
		$refs = array();

		foreach ( array_unique( array_filter( array_map( 'absint', $ids ) ) ) as $id ) {
			if ( $id <= 0 || 'attachment' !== get_post_type( $id ) ) {
				continue;
			}

			$refs[] = array(
				'attachment_id' => $id,
				'detector'      => $detector,
				'label'         => $label,
				'source'        => $source,
				'confidence'    => $confidence,
			);
		}

		return $refs;
	}
}
