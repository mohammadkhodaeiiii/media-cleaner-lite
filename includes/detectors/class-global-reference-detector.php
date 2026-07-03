<?php
/**
 * Global reference detector wrapper.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite\Detectors;

use MediaCleanerLite\ReferenceDetector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delegates to ReferenceDetector global detection methods.
 */
final class GlobalReferenceDetector implements DetectorInterface {

	/**
	 * Reference detector.
	 *
	 * @var ReferenceDetector
	 */
	private ReferenceDetector $detector;

	/**
	 * Constructor.
	 *
	 * @param ReferenceDetector $detector Reference detector.
	 */
	public function __construct( ReferenceDetector $detector ) {
		$this->detector = $detector;
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'global';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'منابع سراسری', 'media-cleaner-lite' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function detect(): array {
		$refs    = array();
		$global  = $this->detector->detect_global();

		foreach ( $global as $attachment_id => $data ) {
			if ( ! is_array( $data ) || empty( $data['sources'] ) ) {
				continue;
			}

			foreach ( (array) $data['sources'] as $source ) {
				if ( ! is_array( $source ) ) {
					continue;
				}

				$refs[] = array(
					'attachment_id' => (int) $attachment_id,
					'source'        => (string) ( $source['source'] ?? '' ),
					'confidence'    => (int) ( $source['confidence'] ?? 80 ),
				);
			}
		}

		return $refs;
	}
}
